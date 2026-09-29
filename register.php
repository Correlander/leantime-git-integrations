<?php

use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Plugins\Services\Registration;
use Leantime\Plugins\GitHubIntegration\Services\TodoGitSection;

$registration = app()->makeWith(Registration::class, ['pluginId' => 'GitHubIntegration']);
$registration->addFooterJs(['github-integration.js']);
$registration->addCss(['github-integration.css']);

EventDispatcher::add_filter_listener(
    'leantime.plugins.leantimelib.project.integrations.panels',
    [TodoGitSection::class, 'registerProjectPanel']
);

EventDispatcher::add_filter_listener(
    'leantime.plugins.leantimelib.todo.detail.sections',
    [TodoGitSection::class, 'registerTodoSection']
);
