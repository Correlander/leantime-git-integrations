<?php

namespace Leantime\Plugins\GitHubIntegration\Services;

class TodoGitSection
{
    public static function registerTodoSection(array $sections, array $params): array
    {
        $sections[] = [
            'id' => 'github',
            'label' => 'GitHub',
            'icon' => 'fa-brands fa-github',
            'order' => 100,
            'render' => static fn ($ticket, array $params): string => app(self::class)->render($ticket),
        ];

        return $sections;
    }

    public function render(mixed $ticket): string
    {
        if (! is_object($ticket) || empty($ticket->id) || empty($ticket->projectId)) return '';
        $ticketId = (int) $ticket->id;
        $projectId = (int) $ticket->projectId;
        return '<div class="github-todo-content" data-github-todo="1" data-ticket-id="'.$ticketId.'" data-project-id="'.$projectId.'" data-csrf="'.htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">'
            .'<p>Loading GitHub information…</p></div>';
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
