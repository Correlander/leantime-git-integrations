<?php

namespace Leantime\Plugins\GitHubIntegration\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Leantime\Core\Auth\Permissions\RequiresPermission;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Core\Exceptions\ValidationException;
use Leantime\Domain\Plugins\Permissions\PluginsPermissions;
use Leantime\Plugins\GitHubIntegration\Services\GitHubStorage;
use Leantime\Plugins\LeantimeLib\Services\SettingsPageRenderer;
use Throwable;

class Settings extends Controller
{
    #[RequiresPermission(PluginsPermissions::MANAGE, global: true)]
    public function get($params)
    {
        $config = app(GitHubStorage::class)->appConfigSummary();
        $this->tpl->assign('clientId', $config['clientId'] ?? '');
        $this->tpl->assign('secretSaved', $config !== null);
        $this->tpl->assign('callbackUrl', rtrim(BASE_URL, '/').'/GitHubIntegration/callback');
        $this->tpl->assign('error', null);
        $this->assignSettingsContent($config['clientId'] ?? '', $config !== null);
        return $this->tpl->display('githubintegration.settings');
    }

    #[RequiresPermission(PluginsPermissions::MANAGE, global: true)]
    public function post($params)
    {
        $input = app(Request::class)->only(['client_id', 'client_secret']);
        try {
            $valid = ValidationException::validate($input, [
                'client_id' => ['required', 'string', 'max:255'],
                'client_secret' => ['nullable', 'string', 'max:4000'],
            ]);
            $storage = app(GitHubStorage::class);
            $currentSummary = $storage->appConfigSummary();
            $secret = trim((string) ($valid['client_secret'] ?? ''));
            if ($secret === '' && ($currentSummary === null || $currentSummary['clientId'] !== trim($valid['client_id']))) {
                throw new \RuntimeException('Enter the GitHub App client secret when setting up or changing the Client ID.');
            }
            if ($secret === '') $secret = $storage->appConfig()['clientSecret'];
            $storage->saveAppConfig(trim($valid['client_id']), $secret);
            $this->tpl->setNotification('GitHub App settings saved.', 'success');
            return Frontcontroller::redirect(BASE_URL.'/GitHubIntegration/settings');
        } catch (Throwable $exception) {
            if (! $exception instanceof ValidationException) {
                Log::error('GitHub Integration settings could not be saved.', [
                    'exception_class' => $exception::class,
                    'exception_code' => (int) $exception->getCode(),
                    'exception_file' => basename($exception->getFile()),
                    'exception_line' => $exception->getLine(),
                ]);
            }
            $config = app(GitHubStorage::class)->appConfigSummary();
            $this->tpl->assign('clientId', trim((string) ($input['client_id'] ?? ($config['clientId'] ?? ''))));
            $this->tpl->assign('secretSaved', $config !== null);
            $this->tpl->assign('callbackUrl', rtrim(BASE_URL, '/').'/GitHubIntegration/callback');
            $this->tpl->assign('error', $exception instanceof ValidationException ? 'Check the submitted fields.' : 'The settings could not be saved.');
            $this->assignSettingsContent(trim((string) ($input['client_id'] ?? ($config['clientId'] ?? ''))), $config !== null);
            return $this->tpl->display('githubintegration.settings');
        }
    }

    private function assignSettingsContent(string $clientId, bool $secretSaved): void
    {
        if (! class_exists(SettingsPageRenderer::class) || SettingsPageRenderer::API_VERSION !== 1) {
            $this->tpl->assign('settingsContent', null);
            return;
        }

        $callbackUrl = rtrim(BASE_URL, '/').'/GitHubIntegration/callback';
        $callbackHtml = static function (array $values = [], array $errors = []) use ($callbackUrl): string {
            $safeUrl = htmlspecialchars($callbackUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<div class="form-group"><label for="github_callback_url">Authorization callback URL</label><input class="form-control" id="github_callback_url" type="text" readonly value="'.$safeUrl.'"><p class="help-block">Register this exact URL as the GitHub App callback URL.</p></div>';
        };
        $settingsHtml = app(SettingsPageRenderer::class)->render([
            'pluginFolder' => 'GitHubIntegration',
            'blocks' => [
                ['type' => 'description', 'text' => 'These credentials identify the company-wide GitHub App. Each Leantime user still authorizes their own GitHub account separately. The client secret is encrypted before storage and never sent back to this page.'],
                ['type' => 'text', 'id' => 'client_id', 'label' => 'GitHub App Client ID', 'required' => true, 'maxlength' => 255],
                ['type' => 'secret', 'id' => 'client_secret', 'label' => 'GitHub App Client Secret', 'maxlength' => 4000, 'autocomplete' => 'new-password'],
                ['type' => 'custom', 'render' => $callbackHtml],
                ['type' => 'description', 'text' => 'Enable user authorization and grant repository Contents read/write plus Pull requests read. Use GitHub App settings to control installation and organization approval.'],
            ],
        ], ['client_id' => $clientId, 'client_secretConfigured' => $secretSaved]);
        $this->tpl->assign('settingsContent', $settingsHtml);
    }
}
