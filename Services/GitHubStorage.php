<?php

namespace Leantime\Plugins\GitHubIntegration\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Contracts\Encryption\DecryptException;
use RuntimeException;

class GitHubStorage
{
    public function ready(): bool
    {
        return Schema::hasTable('zp_github_user_tokens')
            && Schema::hasTable('zp_github_project_connections')
            && Schema::hasTable('zp_github_app_config');
    }

    public function appConfig(): ?array
    {
        if (! $this->ready()) return null;
        $row = DB::table('zp_github_app_config')->where('id', 1)->first();
        if (! $row) return null;
        return ['clientId' => $row->client_id, 'clientSecret' => Crypt::decryptString($row->client_secret)];
    }

    public function appConfigSummary(): ?array
    {
        if (! $this->ready()) return null;
        $row = DB::table('zp_github_app_config')->where('id', 1)->first();
        return $row ? ['clientId' => $row->client_id] : null;
    }

    public function saveAppConfig(string $clientId, string $clientSecret): void
    {
        if (! $this->ready()) throw new RuntimeException('Plugin tables are missing. Disable and re-enable GitHub Integration to run its installer.');
        DB::table('zp_github_app_config')->updateOrInsert(
            ['id' => 1],
            ['client_id' => $clientId, 'client_secret' => Crypt::encryptString($clientSecret), 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function saveUserToken(int $userId, string $githubId, string $login, string $access, ?string $refresh, ?string $accessExpiry, ?string $refreshExpiry): void
    {
        $existing = DB::table('zp_github_user_tokens')->where('leantime_user_id', $userId)->first();
        if ($existing && (string) $existing->github_user_id !== $githubId) {
            DB::table('zp_github_user_tokens')->where('leantime_user_id', $userId)->delete();
        }
        DB::table('zp_github_user_tokens')->updateOrInsert(
            ['github_user_id' => $githubId],
            [
                'leantime_user_id' => $userId,
                'github_login' => $login,
                'access_token' => Crypt::encryptString($access),
                'refresh_token' => $refresh ? Crypt::encryptString($refresh) : null,
                'access_expires_at' => $accessExpiry,
                'refresh_expires_at' => $refreshExpiry,
                'updated_at' => now(),
                'created_at' => $existing?->created_at ?? now(),
            ]
        );
    }

    public function userToken(int $userId): ?array
    {
        $row = DB::table('zp_github_user_tokens')->where('leantime_user_id', $userId)->first();
        if (! $row) return null;
        try {
            return [
                'githubUserId' => (string) $row->github_user_id,
                'login' => $row->github_login,
                'accessToken' => Crypt::decryptString($row->access_token),
                'refreshToken' => $row->refresh_token ? Crypt::decryptString($row->refresh_token) : null,
                'accessExpiresAt' => $row->access_expires_at,
                'refreshExpiresAt' => $row->refresh_expires_at,
            ];
        } catch (DecryptException) {
            // A changed Leantime encryption key invalidates old credentials; let the user relink.
            Log::error('GitHub Integration discarded an undecryptable user credential; the user must reconnect.', [
                'leantime_user_id' => $userId,
                'credential_type' => 'github_user_token',
            ]);
            $this->disconnectUser($userId);
            return null;
        }
    }

    public function disconnectUser(int $userId): void { DB::table('zp_github_user_tokens')->where('leantime_user_id', $userId)->delete(); }
    public function project(int $projectId): ?object { return DB::table('zp_github_project_connections')->where('project_id', $projectId)->first(); }

    public function saveProject(int $projectId, string $owner, string $repo, string $prefix, ?string $base): void
    {
        DB::table('zp_github_project_connections')->updateOrInsert(
            ['project_id' => $projectId],
            ['repository_owner' => $owner, 'repository_name' => $repo, 'branch_prefix' => $prefix, 'base_branch' => $base, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function deleteProject(int $projectId): void { DB::table('zp_github_project_connections')->where('project_id', $projectId)->delete(); }
}
