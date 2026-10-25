@extends('auth.layout')
@section('title', 'Create your account')
@section('body')
    <h1>Create your account</h1>
    <p class="sub">You'll need a verified account to publish a site.</p>
    <form method="post" action="{{ route('register') }}">
        @csrf
        <div class="field"><label for="name">Name</label><input type="text" id="name" name="name" maxlength="80" value="{{ old('name') }}"></div>
        <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" maxlength="120" value="{{ old('email') }}" required autocomplete="email"></div>
        <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required autocomplete="new-password"></div>
        <div class="field"><label for="password_confirmation">Confirm password</label><input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"></div>
        <button type="submit" class="btn">Create account</button>
    </form>
@endsection
@section('foot')Already have an account? <a href="{{ route('login') }}">Log in</a>@endsection
