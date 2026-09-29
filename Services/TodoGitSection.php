<?php

namespace Leantime\Plugins\GitHubIntegration\Services;

class TodoGitSection
{
    public function render(string $event, array $payload): void
    {
        $params = $payload[0] ?? [];
        $ticket = is_array($params) ? ($params['ticket'] ?? null) : null;
        if (! is_object($ticket) || empty($ticket->id) || empty($ticket->projectId)) return;

        $ticketId = (int) $ticket->id;
        $projectId = (int) $ticket->projectId;
        echo '<section class="github-todo-section"><h4><i class="fa-brands fa-github"></i> GitHub</h4>';
        echo '<div class="github-todo-content" data-github-todo="1" data-ticket-id="'.$ticketId.'" data-project-id="'.$projectId.'" data-csrf="'.htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
        echo '<p>Loading GitHub information…</p></div></section>';
    }

    public static function registerProjectPanel(array $panels, array $params): array
    {
        $panels[] = [
            'id' => 'github',
            'label' => 'GitHub',
            'render' => static fn (int $projectId): string => app(ProjectPanel::class)->render($projectId),
        ];

        return $panels;
    }
}
