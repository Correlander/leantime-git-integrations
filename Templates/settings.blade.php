@extends($layout)

@section('content')
<div class="maincontent"><div class="maincontentinner">
    <h1>GitHub Integration</h1>
    <p>Configure the company-wide GitHub App used by this Leantime instance. These credentials identify the App; each Leantime user still authorizes their own GitHub account separately. The client secret is encrypted before it is stored and is never sent back to this page.</p>
    @if (!empty($error)) <div class="alert alert-danger" role="alert">{{ $error }}</div> @endif
    @if (request()->query('github_connected')) <div class="alert alert-success" role="status">GitHub account connected.</div> @endif
    @if (request()->query('github_error')) <div class="alert alert-warning" role="alert">GitHub connection did not complete ({{ request()->query('github_error') }}). Check the App configuration and try again.</div> @endif
    <form method="post" action="{{ BASE_URL }}/GitHubIntegration/settings">
        @csrf
        <div class="form-group"><label for="client_id">GitHub App Client ID</label><input class="form-control" id="client_id" name="client_id" type="text" maxlength="255" required value="{{ $clientId }}"></div>
        <div class="form-group"><label for="client_secret">GitHub App Client Secret</label><input class="form-control" id="client_secret" name="client_secret" type="password" maxlength="4000" autocomplete="new-password" placeholder="{{ $secretSaved ? 'A secret is saved; leave blank to keep it' : 'Enter the App client secret' }}"></div>
        <div class="form-group"><label>Authorization callback URL</label><input class="form-control" type="text" readonly value="{{ $callbackUrl }}"><p class="help-block">Register this exact URL as the GitHub App callback URL.</p></div>
        <p>Enable user authorization and grant repository Contents read/write plus Pull requests read. Use the GitHub App settings to control installation and organization approval.</p>
        <button class="btn btn-primary" type="submit">Save GitHub App settings</button>
    </form>
</div></div>
@endsection
