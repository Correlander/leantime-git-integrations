<?php

namespace Leantime\Plugins\GitHubIntegration\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Leantime\Core\Auth\Permissions\RequiresPermission;
use Leantime\Core\Auth\Permissions\PermissionService;
use Leantime\Core\Exceptions\ValidationException;
use Leantime\Domain\Projects\Permissions\ProjectsPermissions;
use Leantime\Domain\Tickets\Permissions\TicketsPermissions;
use Leantime\Domain\Tickets\Services\Tickets;
use Leantime\Plugins\GitHubIntegration\Services\GitHubApi;
use Leantime\Plugins\GitHubIntegration\Services\GitHubStorage;
use Throwable;

class GitHub
{
    public function __construct(
        private GitHubStorage $storage,
        private GitHubApi $api,
        private PermissionService $permissions,
        private Tickets $tickets
    ) {}

    public function connect(Request $request)
    {
        if ((int) session('userdata.id', 0) < 1) abort(401);
        try { $config = $this->storage->appConfig(); }
        catch (Throwable $exception) {
            $this->logFailure('GitHub App secret could not be decrypted during connect.', $exception);
            return redirect(BASE_URL.'/GitHubIntegration/settings?github_error=secret_key_changed');
        }
        if ($config === null) return redirect(BASE_URL.'/GitHubIntegration/settings');
        $state = Str::random(64);
        $returnTo = (string) $request->query('return_to', '/GitHubIntegration/settings');
        if (! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//') || str_contains($returnTo, '\\') || str_contains($returnTo, "\r") || str_contains($returnTo, "\n")) {
            $returnTo = '/GitHubIntegration/settings';
        }
        session([
            'github_integration.oauth_state' => $state,
            'github_integration.oauth_user' => (int) session('userdata.id'),
            'github_integration.oauth_return_to' => $returnTo,
        ]);
        $query = http_build_query([
            'client_id' => $config['clientId'],
            'redirect_uri' => rtrim(BASE_URL, '/').'/GitHubIntegration/callback',
            'state' => $state,
            'allow_signup' => 'false',
        ]);
        return redirect('https://github.com/login/oauth/authorize?'.$query);
    }

    public function callback(Request $request)
    {
        $expected = (string) session()->pull('github_integration.oauth_state', '');
        $userId = (int) session()->pull('github_integration.oauth_user', 0);
        $returnTo = (string) session()->pull('github_integration.oauth_return_to', '/GitHubIntegration/settings');
        if ($expected === '' || $userId < 1 || ! hash_equals($expected, (string) $request->query('state'))) {
            return $this->oauthRedirect('/GitHubIntegration/settings', 'github_error=invalid_state');
        }
        if ($request->filled('error')) return $this->oauthRedirect($returnTo, 'github_error=authorization_denied');

        try { $config = $this->storage->appConfig(); }
        catch (Throwable $exception) {
            $this->logFailure('GitHub App secret could not be decrypted during OAuth callback.', $exception);
            return $this->oauthRedirect($returnTo, 'github_error=secret_key_changed');
        }
        if ($config === null || ! $request->filled('code')) return $this->oauthRedirect($returnTo, 'github_error=not_configured');

        try {
            $tokenResponse = Http::asForm()->acceptJson()->timeout(15)->post('https://github.com/login/oauth/access_token', [
                'client_id' => $config['clientId'],
                'client_secret' => $config['clientSecret'],
                'code' => $request->query('code'),
            ]);
            if (! $tokenResponse->successful() || ! is_string($tokenResponse->json('access_token'))) {
                Log::error('GitHub OAuth token exchange was rejected.', ['http_status' => $tokenResponse->status()]);
                return $this->oauthRedirect($returnTo, 'github_error=token_exchange');
            }
            $access = $tokenResponse->json('access_token');
            $profile = $this->api->get($access, '/user');
            if (! isset($profile['id'], $profile['login'])) throw new \RuntimeException('GitHub profile response was incomplete.');
            $used = DB::table('zp_github_user_tokens')->where('github_user_id', (string) $profile['id'])->where('leantime_user_id', '<>', $userId)->exists();
            if ($used) return $this->oauthRedirect($returnTo, 'github_error=account_already_linked');
            $expires = $tokenResponse->json('expires_in');
            $refreshExpires = $tokenResponse->json('refresh_token_expires_in');
            $this->storage->saveUserToken(
                $userId,
                (string) $profile['id'],
                (string) $profile['login'],
                $access,
                is_string($tokenResponse->json('refresh_token')) ? $tokenResponse->json('refresh_token') : null,
                is_numeric($expires) ? now()->addSeconds((int) $expires)->toDateTimeString() : null,
                is_numeric($refreshExpires) ? now()->addSeconds((int) $refreshExpires)->toDateTimeString() : null
            );
            return $this->oauthRedirect($returnTo, 'github_connected=1');
        } catch (Throwable $exception) {
            $this->logFailure('GitHub OAuth callback failed.', $exception, ['leantime_user_id' => $userId]);
            return $this->oauthRedirect($returnTo, 'github_error=connection_failed');
        }
    }

    public function disconnect()
    {
        $userId = (int) session('userdata.id');
        if ($userId < 1) abort(401);
        $token = $this->storage->userToken($userId);
        try { $config = $this->storage->appConfig(); }
        catch (Throwable $exception) {
            $this->logFailure('GitHub App secret could not be decrypted during disconnect.', $exception, ['leantime_user_id' => $userId]);
            $config = null;
        }
        if ($token && $config) {
            try { $this->api->deleteToken($config['clientId'], $config['clientSecret'], $token['accessToken']); }
            catch (Throwable $exception) { $this->logFailure('GitHub token revocation failed.', $exception, ['leantime_user_id' => $userId]); }
        }
        $this->storage->disconnectUser($userId);
        return response()->json(['ok' => true]);
    }

    #[RequiresPermission(ProjectsPermissions::VIEW, projectIdParam: 'projectId')]
    public function projectPanel(int $projectId)
    {
        $connection = $this->storage->project($projectId);
        $token = $this->storage->userToken((int) session('userdata.id'));
        return view('githubintegration::projectPanel', [
            'projectId' => $projectId,
            'connection' => $connection,
            'githubLogin' => $token['login'] ?? null,
            'csrfToken' => csrf_token(),
            'appConfigured' => $this->storage->appConfigSummary() !== null,
            'canConfigure' => $this->permissions->currentUserCan(ProjectsPermissions::EDIT, null, true),
        ]);
    }

    #[RequiresPermission(ProjectsPermissions::EDIT, global: true)]
    public function saveProject(Request $request, int $projectId)
    {
        $validated = ValidationException::validate($request->only(['owner', 'repository', 'branch_prefix', 'base_branch']), [
            'owner' => ['required', 'string', 'max:39', 'regex:/^[A-Za-z0-9-]+$/'],
            'repository' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'branch_prefix' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/'],
            'base_branch' => ['nullable', 'string', 'max:255'],
        ]);
        $baseBranch = trim((string) ($validated['base_branch'] ?? ''));
        if ($baseBranch !== '' && (str_contains($baseBranch, '..') || str_ends_with($baseBranch, '/') || preg_match('/[\s~^:?*\[\x5C]/', $baseBranch))) {
            return response()->json(['error' => 'The base branch name contains unsupported characters.'], 422);
        }
        $token = $this->accessToken((int) session('userdata.id'));
        if ($token === null) return response()->json(['error' => 'Connect your GitHub account before saving a repository.'], 409);

        try {
            $repo = $this->api->get($token, '/repos/'.rawurlencode($validated['owner']).'/'.rawurlencode($validated['repository']));
            if (empty($repo['full_name'])) return response()->json(['error' => 'GitHub repository could not be verified.'], 422);
            $this->storage->saveProject(
                $projectId,
                (string) $repo['owner']['login'],
                (string) $repo['name'],
                strtolower(trim($validated['branch_prefix'])),
                $baseBranch ?: null
            );
            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            $this->logFailure('GitHub repository verification failed.', $exception, ['project_id' => $projectId]);
            return response()->json(['error' => 'Could not verify this repository. Check its name and your GitHub access.'], 422);
        }
    }

    #[RequiresPermission(ProjectsPermissions::EDIT, global: true)]
    public function deleteProject(int $projectId)
    {
        $this->storage->deleteProject($projectId);
        return response()->json(['ok' => true]);
    }

    #[RequiresPermission(TicketsPermissions::VIEW, entityScoped: true)]
    public function todoData(int $ticketId)
    {
        $ticket = $this->authorizedTicket($ticketId, TicketsPermissions::VIEW);
        if ($ticket === null) return response()->json(['error' => 'To-do not found or unavailable.'], 404);
        $userId = (int) session('userdata.id');
        $connection = $this->storage->project((int) $ticket->projectId);
        $canCreate = $this->permissions->currentUserCan(TicketsPermissions::EDIT, (int) $ticket->projectId);
        $appConfigured = $this->storage->appConfigSummary() !== null;
        if (! $connection) return response()->json(['connected' => false, 'appConfigured' => $appConfigured, 'canCreateBranch' => $canCreate, 'branches' => [], 'pullRequests' => []]);
        $token = $this->accessToken($userId);
        if ($token === null) return response()->json(['connected' => true, 'githubLinked' => false, 'appConfigured' => $appConfigured, 'canCreateBranch' => $canCreate, 'branches' => [], 'pullRequests' => []]);

        try {
            $marker = $this->branchMarker($connection->branch_prefix, (int) $ticket->projectId, $ticketId);
            $cacheKey = 'github.todo.'.$userId.'.'.$ticketId;
            $data = Cache::remember($cacheKey, now()->addSeconds(30), function () use ($token, $connection, $marker) {
                $repo = rawurlencode($connection->repository_owner).'/'.rawurlencode($connection->repository_name);
                $branches = $this->api->get($token, '/repos/'.$repo.'/branches?per_page=100');
                $pulls = $this->api->get($token, '/repos/'.$repo.'/pulls?state=all&per_page=100');
                return [
                    'branches' => array_values(array_map(static fn ($item) => ['name' => $item['name'], 'url' => 'https://github.com/'.$connection->repository_owner.'/'.$connection->repository_name.'/tree/'.rawurlencode($item['name'])], array_filter($branches, static fn ($item) => isset($item['name']) && str_starts_with($item['name'], $marker)))),
                    'pullRequests' => array_values(array_map(static fn ($item) => ['title' => $item['title'], 'url' => $item['html_url'], 'number' => $item['number'], 'state' => $item['state'], 'branch' => $item['head']['ref']], array_filter($pulls, static fn ($item) => isset($item['head']['ref']) && str_starts_with($item['head']['ref'], $marker)))),
                ];
            });
            return response()->json(['connected' => true, 'githubLinked' => true, 'appConfigured' => $appConfigured, 'canCreateBranch' => $canCreate, 'repository' => $connection->repository_owner.'/'.$connection->repository_name] + $data);
        } catch (Throwable $exception) {
            $this->logFailure('GitHub To-do data request failed.', $exception, ['ticket_id' => $ticketId, 'project_id' => (int) $ticket->projectId]);
            if (in_array((int) $exception->getCode(), [403, 404], true)) {
                return response()->json(['error' => 'Your linked GitHub account cannot access this repository, or GitHub has temporarily rate-limited this request.'], 403);
            }
            return response()->json(['error' => 'GitHub information is temporarily unavailable.'], 502);
        }
    }

    #[RequiresPermission(TicketsPermissions::EDIT, entityScoped: true)]
    public function createBranch(Request $request, int $ticketId)
    {
        $ticket = $this->authorizedTicket($ticketId, TicketsPermissions::EDIT);
        if ($ticket === null) return response()->json(['error' => 'To-do not found or you cannot edit it.'], 404);
        try {
            $input = ValidationException::validate($request->only(['summary']), ['summary' => ['required', 'string', 'max:100']]);
            $slug = Str::slug(trim($input['summary']));
            if ($slug === '') return response()->json(['error' => 'Enter a short branch description using letters or numbers.'], 422);
            $connection = $this->storage->project((int) $ticket->projectId);
            if (! $connection) return response()->json(['error' => 'This project has no GitHub repository configured.'], 409);
            $token = $this->accessToken((int) session('userdata.id'));
            if ($token === null) return response()->json(['error' => 'Connect your GitHub account before creating a branch.'], 409);

            $branch = $this->branchMarker($connection->branch_prefix, (int) $ticket->projectId, $ticketId).substr($slug, 0, 60);
            $repo = rawurlencode($connection->repository_owner).'/'.rawurlencode($connection->repository_name);
            $repoInfo = $this->api->get($token, '/repos/'.$repo);
            $base = $connection->base_branch ?: ($repoInfo['default_branch'] ?? 'main');
            $baseRef = $this->api->get($token, '/repos/'.$repo.'/git/ref/heads/'.rawurlencode($base));
            $created = $this->api->post($token, '/repos/'.$repo.'/git/refs', ['ref' => 'refs/heads/'.$branch, 'sha' => $baseRef['object']['sha']]);
            Cache::forget('github.todo.'.(int) session('userdata.id').'.'.$ticketId);
            return response()->json(['ok' => true, 'name' => $branch, 'url' => $created['url'] ?? null]);
        } catch (Throwable $exception) {
            $this->logFailure('GitHub branch creation failed.', $exception, ['ticket_id' => $ticketId, 'project_id' => (int) $ticket->projectId]);
            return response()->json(['error' => 'Branch creation failed. Check that your GitHub account can write to the connected repository and that the base branch exists.'], 422);
        }
    }

    private function authorizedTicket(int $ticketId, string $permission): ?object
    {
        $ticket = $this->tickets->getTicket($ticketId);
        if (! is_object($ticket)) return null;
        $this->permissions->authorize($permission, (int) $ticket->projectId);
        return $ticket;
    }

    private function oauthRedirect(string $returnTo, string $query): mixed
    {
        if (! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//') || str_contains($returnTo, '\\') || str_contains($returnTo, "\r") || str_contains($returnTo, "\n")) {
            $returnTo = '/GitHubIntegration/settings';
        }
        [$path, $fragment] = array_pad(explode('#', $returnTo, 2), 2, null);
        $separator = str_contains($path, '?') ? '&' : '?';
        return redirect(rtrim(BASE_URL, '/').$path.$separator.$query.($fragment !== null ? '#'.$fragment : ''));
    }

    private function branchMarker(string $prefix, int $projectId, int $ticketId): string
    {
        return trim($prefix, '-').'-p'.$projectId.'-t'.$ticketId.'-';
    }

    private function accessToken(int $userId): ?string
    {
        $token = $this->storage->userToken($userId);
        if (! $token) return null;
        if (empty($token['accessExpiresAt']) || now()->lt($token['accessExpiresAt'])) return $token['accessToken'];
        if (empty($token['refreshToken']) || empty($token['refreshExpiresAt']) || now()->gte($token['refreshExpiresAt'])) return null;

        try { $config = $this->storage->appConfig(); }
        catch (Throwable $exception) {
            $this->logFailure('GitHub App secret could not be decrypted while refreshing a user token.', $exception, ['leantime_user_id' => $userId]);
            return null;
        }
        if (! $config) return null;
        try {
            $response = Http::asForm()->acceptJson()->timeout(15)->post('https://github.com/login/oauth/access_token', [
                'client_id' => $config['clientId'],
                'client_secret' => $config['clientSecret'],
                'grant_type' => 'refresh_token',
                'refresh_token' => $token['refreshToken'],
            ]);
        } catch (Throwable $exception) {
            $this->logFailure('GitHub user token refresh request failed.', $exception, ['leantime_user_id' => $userId]);
            return null;
        }
        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            Log::error('GitHub rejected a user token refresh.', ['leantime_user_id' => $userId, 'http_status' => $response->status()]);
            return null;
        }
        $newAccess = $response->json('access_token');
        $newRefresh = $response->json('refresh_token');
        $this->storage->saveUserToken(
            $userId, $token['githubUserId'], $token['login'], $newAccess,
            is_string($newRefresh) ? $newRefresh : $token['refreshToken'],
            now()->addSeconds((int) $response->json('expires_in', 28800))->toDateTimeString(),
            now()->addSeconds((int) $response->json('refresh_token_expires_in', 15811200))->toDateTimeString()
        );
        return $newAccess;
    }

    /** Log useful diagnostics without recording credentials, request bodies, or provider response bodies. */
    private function logFailure(string $message, Throwable $exception, array $context = []): void
    {
        Log::error($message, $context + [
            'exception_class' => $exception::class,
            'exception_code' => (int) $exception->getCode(),
            'exception_file' => basename($exception->getFile()),
            'exception_line' => $exception->getLine(),
        ]);
    }
}
