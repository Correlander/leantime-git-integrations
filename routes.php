<?php

use Illuminate\Support\Facades\Route;
use Leantime\Plugins\LeanGitHub\Controllers\GitHub;
use Leantime\Plugins\LeanGitHub\Controllers\Settings;

Route::get('/LeanGitHub/settings', [Settings::class, 'get'])->name('leanGithub.settings');
Route::post('/LeanGitHub/settings', [Settings::class, 'post'])->name('leanGithub.settings.save');
Route::get('/LeanGitHub/connect', [GitHub::class, 'connect'])->name('leanGithub.connect');
Route::get('/LeanGitHub/callback', [GitHub::class, 'callback'])->name('leanGithub.callback');
Route::delete('/LeanGitHub/disconnect', [GitHub::class, 'disconnect'])->name('leanGithub.disconnect');
Route::post('/LeanGitHub/projects/{projectId}/save', [GitHub::class, 'saveProject'])->name('leanGithub.project.save');
Route::delete('/LeanGitHub/projects/{projectId}', [GitHub::class, 'deleteProject'])->name('leanGithub.project.delete');
Route::get('/LeanGitHub/projects/{projectId}/panel', [GitHub::class, 'projectPanel'])->name('leanGithub.project.panel');
Route::get('/LeanGitHub/todos/{ticketId}', [GitHub::class, 'todoData'])->name('leanGithub.todo.data');
Route::post('/LeanGitHub/todos/{ticketId}/branches', [GitHub::class, 'createBranch'])->name('leanGithub.todo.branch');

// Keep old callback and UI URLs working for existing GitHub App registrations and bookmarks.
Route::get('/GitHubIntegration/settings', [Settings::class, 'get']);
Route::post('/GitHubIntegration/settings', [Settings::class, 'post']);
Route::get('/GitHubIntegration/connect', [GitHub::class, 'connect']);
Route::get('/GitHubIntegration/callback', [GitHub::class, 'callback']);
Route::delete('/GitHubIntegration/disconnect', [GitHub::class, 'disconnect']);
Route::post('/GitHubIntegration/projects/{projectId}/save', [GitHub::class, 'saveProject']);
Route::delete('/GitHubIntegration/projects/{projectId}', [GitHub::class, 'deleteProject']);
Route::get('/GitHubIntegration/projects/{projectId}/panel', [GitHub::class, 'projectPanel']);
Route::get('/GitHubIntegration/todos/{ticketId}', [GitHub::class, 'todoData']);
Route::post('/GitHubIntegration/todos/{ticketId}/branches', [GitHub::class, 'createBranch']);
