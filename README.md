# lean-github

This project contains one provider plugin for Leantime 3.10.0. It uses the existing **lean-lib** plugin as the central integration-panel registry. There is no second Hub plugin.

## Deployment folders

The files in this project are the GitHub provider plugin. Install them so `composer.json` is at `app/Plugins/LeanGitHub/composer.json`. The Composer package name is `lean-github`; Leantime's installed folder/ID is `LeanGitHub` because it derives PHP namespaces and lifecycle class names from that folder. Copy the project contents into `app/Plugins/LeanGitHub/` (do not create an extra nested folder).

Install and enable `LeanLib` (lean-lib) version `0.19.0` or later first. lean-github uses the Library's shared settings page builder and contribution registries; it does not bundle duplicate settings UI or a fallback form.

Existing installations use the previous folder ID `GitHubIntegration`. Leantime treats `LeanGitHub` as a new plugin record. Disable the old entry, install and enable `LeanGitHub`, and verify the GitHub settings and user connection. Do not uninstall the old entry during migration if you need its plugin tables and stored credentials; the new plugin's installer reuses existing tables. The registered OAuth callback remains `/GitHubIntegration/callback`.

Enable both plugins for the full experience. LeanGitHub contributes its Project Settings panel and To-do section through LeanLib's registries; LeanLib places the section at Leantime's supported after-Schedule hook and applies the Library-defined order. Other GitHub OAuth/API routes remain independent, but the settings page and UI contributions require LeanLib enabled.

If LeanLib is later disabled while LeanGitHub remains enabled, neither GitHub UI contribution will render: Leantime's native Project Settings → Integrations content remains, and no GitHub section is added to the To-do modal. The GitHub backend routes still load. Disabling LeanLib does not uninstall LeanGitHub, invoke its uninstall handler, remove its tables, or delete GitHub settings/tokens. Re-enable LeanLib to restore both centrally registered contributions.

## First setup

1. Install a **GitHub App** on GitHub.com with user authorization enabled. Set repository permissions to **Contents: Read and write** and **Pull requests: Read-only**. Limit installation to the repositories this Leantime instance should access.
2. Copy the callback URL shown in **My Apps → GitHub Integration → Settings** into the GitHub App's callback URL.
3. Enter its Client ID and Client Secret on that settings page. These credentials are intentionally instance-wide: they identify one GitHub App for the Leantime company. Each user's GitHub access token is still separate and encrypted in the provider's tables. The Client Secret is never rendered back to the browser. Keep Leantime's encryption key backed up and stable; changing it requires replacing the saved App secret and reconnecting each GitHub user.
4. Each user connects their own GitHub account from a project's **Integrations → GitHub** panel. GitHub actions use that user's authorization and repository access.
5. A project administrator configures one repository, a branch prefix, and optionally a base branch in that panel. Blank base branch means the GitHub repository's default branch.

## Current behavior

- Registers a Git section with LeanLib; the Library renders contributed inline sections at Leantime's native `beforeEndRightColumn` hook after Schedule and applies the saved contribution order.
- Displays up to the first 100 branches and pull requests whose head branch starts with `{prefix}-p{projectId}-t{todoId}-`.
- Creates a branch from the chosen base branch when the user has project-scoped `tickets.edit` for that To-do and their linked GitHub account can write to the repository. The branch name is `{prefix}-p{projectId}-t{todoId}-{description-slug}`.
- Shows integration panels through LeanLib's `leantime.plugins.leantimelib.project.integrations.panels` filter. LeanLib replaces the stock Project Settings Integrations panel body while preserving the rest of the native project page.
- Caches each user's To-do GitHub response for 30 seconds. Tokens stay server-side and are encrypted in plugin-owned tables.
- Uses an idempotent plugin-managed schema version recorded in `zp_github_schema_migrations`; disabling the plugin preserves its tables.

## Design choices for this first implementation

- GitHub.com only; no GHES/custom API hosts.
- One repository per Leantime project.
- At most one GitHub identity per Leantime user, and a GitHub identity cannot be linked to multiple Leantime users in the same instance.
- Instance managers configure the GitHub App. Project repository configuration uses Leantime's global `projects.edit` permission (manager+ by default). Task branch creation requires project-scoped `tickets.edit`.
- Disconnecting a GitHub account attempts to revoke its user authorization, then removes its local token. Disconnecting a project removes the local mapping only; remote branches are never deleted.
- Disabling preserves plugin data. Uninstalling LeanGitHub deletes its three plugin-owned tables. Back up before uninstall if configuration should be retained.
- GitHub data association is inferred from the branch naming convention; this version does not store task-to-branch links or listen for webhook updates.
- A GitHub API outage does not serve stale results; the To-do section displays a recoverable error and can be retried by reopening the To-do.
- Leantime 3.10.0 does not expose a typed project-deleted hook in the inspected source; delete the project repository mapping in its Integrations panel before deleting that Leantime project. Uninstall still purges all plugin tables.

## Contributor contract

The provider contributes its Project Settings panel to LeanLib using `leantime.plugins.leantimelib.project.integrations.panels`. The Library supplies the shared title/description/divider/content frame, panel order, and project-specific order override. Provider controllers own their permissions, validation, storage, and secret handling. The GitHub settings route uses the Library's shared settings blocks; the provider retains only its route, field data, validation, and save logic.

## Troubleshooting logs

The provider writes operational failures through Leantime's Laravel logger at `error` level, including OAuth exchange/refresh failures, GitHub API failures, settings persistence errors, and credential-decryption recovery. It does not log OAuth tokens, the Client Secret, request bodies, or full GitHub response bodies. The Library also logs provider-panel/tab rendering exceptions and invalid contributions.

On the default Leantime 3.10.0 logging configuration, the daily application log is under `storage/logs/leantime-YYYY-MM-DD.log` (five days retained). From the Leantime root, find the latest file and follow it while reproducing an issue:

```sh
ls -lt storage/logs/leantime-*.log | head
tail -F storage/logs/leantime-$(date +%F).log
```

If the file is absent or does not receive entries, check `LEAN_LOG_CHANNELS` in the Leantime environment: an installation can route its stack to syslog/Sentry or customize/remove the `single` file channel. Browser-side failures are also printed in DevTools Console with the `[LeanGitHub]` prefix.

## License

All rights reserved. See [LICENSE](LICENSE).
