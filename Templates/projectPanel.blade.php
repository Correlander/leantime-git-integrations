<div class="github-project-panel" data-github-project="{{ $projectId }}" data-base-url="{{ rtrim(BASE_URL, '/') }}" data-csrf="{{ $csrfToken }}">
    @if (!empty($oauthError)) <div class="alert alert-warning" role="alert">GitHub connection did not complete ({{ $oauthError }}). Please try again.</div> @endif
    @if (!empty($oauthConnected)) <div class="alert alert-success" role="status">GitHub account connected.</div> @endif
    @if (!$appConfigured)
        <div class="alert alert-info">An administrator must configure the GitHub App before accounts can connect.</div>
        @if ($canConfigure) <p><a href="{{ BASE_URL }}/LeanGitHub/settings">Configure GitHub Integration</a></p> @endif
    @elseif (!$githubLogin)
        <p>Connect your own GitHub account to verify repository access and use this integration.</p>
        <a class="btn btn-default" href="{{ BASE_URL }}/LeanGitHub/connect?return_to={{ urlencode('/projects/showProject/'.$projectId.'#integrations') }}">Connect GitHub account</a>
    @else
        <p>Connected GitHub account: <strong>{{ $githubLogin }}</strong></p>
        <button type="button" class="btn btn-default" data-github-disconnect>Disconnect my GitHub account</button>
    @endif

    @if ($connection)
        <p>Connected repository: <a href="https://github.com/{{ rawurlencode($connection->repository_owner) }}/{{ rawurlencode($connection->repository_name) }}" target="_blank" rel="noopener">{{ $connection->repository_owner }}/{{ $connection->repository_name }}</a></p>
    @else
        <p>No repository is configured for this project yet.</p>
    @endif

    @if ($canConfigure)
    <form data-github-project-form method="post" action="{{ BASE_URL }}/LeanGitHub/projects/{{ $projectId }}/save">
        @csrf
        <div class="row">
            <div class="col-md-3"><label>Repository owner</label><input class="form-control" name="owner" required maxlength="255" value="{{ $connection->repository_owner ?? '' }}"></div>
            <div class="col-md-3"><label>Repository name</label><input class="form-control" name="repository" required maxlength="255" value="{{ $connection->repository_name ?? '' }}"></div>
            <div class="col-md-3"><label>Branch prefix</label><input class="form-control" name="branch_prefix" required maxlength="40" value="{{ $connection->branch_prefix ?? 'lt' }}"></div>
            <div class="col-md-3"><label>Default base branch</label><input class="form-control" name="base_branch" maxlength="255" placeholder="Repository default" value="{{ $connection->base_branch ?? '' }}"></div>
        </div>
        <p class="help-block">Branch format: <code>{prefix}-p{projectId}-t{todoId}-{description}</code>. Leave the base branch blank to use the repository default.</p>
        <button class="btn btn-primary" type="submit" @if (!$githubLogin) disabled @endif>Save repository settings</button>
        <span data-github-save-status role="status"></span>
    </form>
    @elseif (!$connection)
        <p>Ask a project administrator to configure the repository.</p>
    @endif
    @if ($connection && $canConfigure)
        <button type="button" class="btn btn-default" data-github-project-disconnect="{{ $projectId }}">Disconnect repository</button>
        <span data-github-project-status role="status"></span>
    @endif
</div>
