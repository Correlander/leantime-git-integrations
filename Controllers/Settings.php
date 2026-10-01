<?php

namespace Leantime\Plugins\LeanGitHub\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Leantime\Core\Auth\Permissions\RequiresPermission;
use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Core\Exceptions\ValidationException;
use Leantime\Domain\Plugins\Permissions\PluginsPermissions;
use Leantime\Plugins\LeanGitHub\Services\GitHubStorage;
use Leantime\Plugins\LeanLib\Services\SettingsPageRenderer;
use Leantime\Plugins\LeanLib\Services\SettingsPage;
use Leantime\Plugins\LeanLib\Services\SettingsPageBlock;
use Throwable;

class Settings extends Controller
{
    #[RequiresPermission(PluginsPermissions::MANAGE, global: true)]
    public function get($params)
    {
        $config = app(GitHubStorage::class)->appConfigSummary();
        $this->tpl->assign('error', null);
        $this->assignSettingsContent($config['clientId'] ?? '', $config !== null);
        return $this->tpl->display('leangithub.settings');
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
            return Frontcontroller::redirect(BASE_URL.'/LeanGitHub/settings');
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
            $this->tpl->assign('error', $exception instanceof ValidationException ? 'Check the submitted fields.' : 'The settings could not be saved.');
            $this->assignSettingsContent(trim((string) ($input['client_id'] ?? ($config['clientId'] ?? ''))), $config !== null);
            return $this->tpl->display('leangithub.settings');
        }
    }

    private function assignSettingsContent(string $clientId, bool $secretSaved): void
    {
        if (SettingsPageRenderer::API_VERSION !== 3 || SettingsPage::API_VERSION !== 3) {
            throw new \RuntimeException('lean-github requires lean-lib 0.19.0 or later for shared settings rendering.');
        }

        $callbackUrl = rtrim(BASE_URL, '/').'/GitHubIntegration/callback';
        $callbackHtml = static function (array $values = [], array $errors = []) use ($callbackUrl): string {
            $safeUrl = htmlspecialchars($callbackUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<div class="form-group"><label for="github_callback_url">Authorization callback URL</label><input class="form-control" id="github_callback_url" type="text" readonly value="'.$safeUrl.'"><p class="help-block">Register this exact URL as the GitHub App callback URL.</p></div>';
        };
        $settingsHtml = SettingsPage::forPlugin('LeanGitHub')
            ->footer(false, 'Save GitHub App settings')
            ->insert(
                SettingsPageBlock::description('These credentials identify the company-wide GitHub App. Each Leantime user still authorizes their own GitHub account separately. The client secret is encrypted before storage and never sent back to this page.'),
                SettingsPageBlock::text('client_id', 'GitHub App Client ID', ['required' => true, 'maxlength' => 255]),
                SettingsPageBlock::secret('client_secret', 'GitHub App Client Secret', ['maxlength' => 4000, 'autocomplete' => 'new-password']),
                SettingsPageBlock::custom($callbackHtml),
                SettingsPageBlock::description('Enable user authorization and grant repository Contents read/write plus Pull requests read. Use GitHub App settings to control installation and organization approval.')
            )
            ->render(['client_id' => $clientId, 'client_secretConfigured' => $secretSaved]);
        $this->tpl->assign('settingsContent', $settingsHtml);
    }
}
