<?php

use Illuminate\Support\Facades\Route;
use Leantime\Plugins\GitHubIntegration\Controllers\GitHub;
use Leantime\Plugins\GitHubIntegration\Controllers\Settings;

Route::get('/GitHubIntegration/settings', [Settings::class, 'get'])->name('githubIntegration.settings');
Route::post('/GitHubIntegration/settings', [Settings::class, 'post'])->name('githubIntegration.settings.save');
Route::get('/GitHubIntegration/connect', [GitHub::class, 'connect'])->name('githubIntegration.connect');
Route::get('/GitHubIntegration/callback', [GitHub::class, 'callback'])->name('githubIntegration.callback');
Route::delete('/GitHubIntegration/disconnect', [GitHub::class, 'disconnect'])->name('githubIntegration.disconnect');
Route::post('/GitHubIntegration/projects/{projectId}/save', [GitHub::class, 'saveProject'])->name('githubIntegration.project.save');
Route::delete('/GitHubIntegration/projects/{projectId}', [GitHub::class, 'deleteProject'])->name('githubIntegration.project.delete');
Route::get('/GitHubIntegration/projects/{projectId}/panel', [GitHub::class, 'projectPanel'])->name('githubIntegration.project.panel');
Route::get('/GitHubIntegration/todos/{ticketId}', [GitHub::class, 'todoData'])->name('githubIntegration.todo.data');
Route::post('/GitHubIntegration/todos/{ticketId}/branches', [GitHub::class, 'createBranch'])->name('githubIntegration.todo.branch');
