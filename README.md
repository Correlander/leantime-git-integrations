# Leantime GitHub Integrations

This repository contains one provider plugin for Leantime 3.10.0. It uses the existing **LeantimeLib** plugin as the central integration-panel registry. There is no second Hub plugin.

## Deployment folders

Copy these two folders directly under the Leantime `app/Plugins/` directory. Do not copy the repository parent as the plugin directory:

- `app/Plugins/GitHubIntegration/`
- Update the existing `app/Plugins/LeantimeLib/` from the LeantimeLib project to version `0.2.0` or later.

Enable LeantimeLib before enabling GitHubIntegration. Both plugins must remain enabled for the project Integrations panel and To-do section to work.

## First setup

1. Install a **GitHub App** on GitHub.com with user authorization enabled. Set repository permissions to **Contents: Read and write** and **Pull requests: Read-only**. Limit installation to the repositories this Leantime instance should access.
2. Copy the callback URL shown in **My Apps → GitHub Integration → Settings** into the GitHub App's callback URL.
3. Enter its Client ID and Client Secret on that settings page. The Client Secret is encrypted with Leantime's configured Laravel encrypter and is never rendered back to the browser. Keep Leantime's encryption key backed up and stable; changing it requires replacing the saved App secret and reconnecting each GitHub user.
4. Each user connects their own GitHub account from a project's **Integrations → GitHub** panel. GitHub actions use that user's authorization and repository access.
5. A project administrator configures one repository, a branch prefix, and optionally a base branch in that panel. Blank base branch means the GitHub repository's default branch.

## Current behavior

- Adds a Git section after Schedule in Leantime's To-do detail modal using the native `beforeEndRightColumn` hook.
- Displays up to the first 100 branches and pull requests whose head branch starts with `{prefix}-p{projectId}-t{todoId}-`.
- Creates a branch from the chosen base branch when the user has project-scoped `tickets.edit` for that To-do and their linked GitHub account can write to the repository. The branch name is `{prefix}-p{projectId}-t{todoId}-{description-slug}`.
- Shows integration panels through LeantimeLib's `leantime.plugins.leantimelib.project.integrations.panels` filter. LeantimeLib replaces the stock Project Settings Integrations panel body while preserving the rest of the native project page.
- Caches each user's To-do GitHub response for 30 seconds. Tokens stay server-side and are encrypted in plugin-owned tables.
- Uses an idempotent plugin-managed schema version recorded in `zp_github_schema_migrations`; disabling the plugin preserves its tables.

## Design choices for this first implementation

- GitHub.com only; no GHES/custom API hosts.
- One repository per Leantime project.
- At most one GitHub identity per Leantime user, and a GitHub identity cannot be linked to multiple Leantime users in the same instance.
- Instance managers configure the GitHub App. Project repository configuration uses Leantime's global `projects.edit` permission (manager+ by default). Task branch creation requires project-scoped `tickets.edit`.
- Disconnecting a GitHub account attempts to revoke its user authorization, then removes its local token. Disconnecting a project removes the local mapping only; remote branches are never deleted.
- Disabling preserves plugin data. Uninstalling GitHubIntegration deletes its three plugin-owned tables. Back up before uninstall if configuration should be retained.
- GitHub data association is inferred from the branch naming convention; this version does not store task-to-branch links or listen for webhook updates.
- A GitHub API outage does not serve stale results; the To-do section displays a recoverable error and can be retried by reopening the To-do.
- Leantime 3.10.0 does not expose a typed project-deleted hook in the inspected source; delete the project repository mapping in its Integrations panel before deleting that Leantime project. Uninstall still purges all plugin tables.

## Contributor contract

The provider contributes its Project Settings panel to LeantimeLib using `leantime.plugins.leantimelib.project.integrations.panels`. Other provider plugins should register their own stable ID, label, and trusted panel renderer through that same Library filter. Provider controllers own their permissions, validation, storage, and secret handling.
