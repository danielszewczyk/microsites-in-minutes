@extends('auth.layout')
@section('title', 'Log in')
@section('body')
    @php($prefillEmail = is_string(request('email')) ? request('email') : null)
    <h1>Log in</h1>
    <p class="sub">Welcome back.</p>
    <form method="post" action="{{ route('login') }}">
        @csrf
        <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="{{ old('email', $prefillEmail) }}" required autocomplete="email"></div>
        <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required autocomplete="current-password"></div>
        <label class="muted" style="font-weight:400"><input type="checkbox" name="remember" value="1" style="width:auto"> Remember me</label>
        <button type="submit" class="btn" style="margin-top:14px">Log in</button>
    </form>
@endsection
@section('foot')No account yet? <a href="{{ route('register', array_filter(['email' => $prefillEmail])) }}">Create one</a>@endsection
