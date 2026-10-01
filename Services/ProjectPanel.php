<?php

namespace Leantime\Plugins\LeanGitHub\Services;

use Leantime\Core\Auth\Permissions\PermissionService;
use Leantime\Domain\Projects\Permissions\ProjectsPermissions;

class ProjectPanel
{
    public function __construct(private GitHubStorage $storage, private PermissionService $permissions) {}

    public function data(int $projectId): array
    {
        $connection = $this->storage->project($projectId);
        $linked = $this->storage->userToken((int) session('userdata.id'));
        return [
            'projectId' => $projectId,
            'connection' => $connection,
            'githubLogin' => $linked['login'] ?? null,
            'csrfToken' => csrf_token(),
            'appConfigured' => $this->storage->appConfigSummary() !== null,
            'canConfigure' => $this->permissions->currentUserCan(ProjectsPermissions::EDIT, null, true),
            'oauthError' => request()->query('github_error'),
            'oauthConnected' => request()->query('github_connected'),
        ];
    }
}
