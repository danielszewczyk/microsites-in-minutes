@extends('auth.layout')
@section('title', 'Connect '.$client->name)
@section('body')
    <h1>Authorize {{ $client->name }}</h1>
    <p class="sub">Signed in as <strong>{{ auth()->user()?->email }}</strong>.</p>
    <p class="muted" style="margin-bottom:18px">
        <strong>{{ $client->name }}</strong> wants to connect to your Microsites in Minutes account so it can create and
        manage your microsites.
        @if (count($scopes) > 0)
            <br><br>It is requesting:
            <ul>@foreach ($scopes as $scope)<li>{{ $scope->description }}</li>@endforeach</ul>
        @endif
    </p>

    <form method="post" action="{{ route('passport.authorizations.approve') }}">
        @csrf
        <input type="hidden" name="state" value="{{ $request->state }}">
        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
        <button type="submit" class="btn">Allow</button>
    </form>
    <form method="post" action="{{ route('passport.authorizations.deny') }}" style="margin-top:10px">
        @csrf
        @method('DELETE')
        <input type="hidden" name="state" value="{{ $request->state }}">
        <input type="hidden" name="client_id" value="{{ $client->getKey() }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
        <button type="submit" class="btn" style="background:transparent;color:var(--muted);border:1px solid var(--line)">Cancel</button>
    </form>
@endsection
@section('foot')You can disconnect this connector at any time.@endsection
