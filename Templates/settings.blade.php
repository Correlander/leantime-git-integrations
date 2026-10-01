@extends($layout)

@section('content')
<div class="maincontent"><div class="maincontentinner">
    @if (!empty($error)) <div class="alert alert-danger" role="alert">{{ $error }}</div> @endif
    @if (request()->query('github_connected')) <div class="alert alert-success" role="status">GitHub account connected.</div> @endif
    @if (request()->query('github_error')) <div class="alert alert-warning" role="alert">GitHub connection did not complete ({{ request()->query('github_error') }}). Check the App configuration and try again.</div> @endif
    <form method="post" action="{{ BASE_URL }}/LeanGitHub/settings">
        @csrf
        {!! $settingsContent !!}
    </form>
</div></div>
@endsection
