@extends('auth.layout')
@section('title', 'Verify your email')
@section('body')
    <h1>Verify your email</h1>
    <p class="sub">We sent a link to <strong>{{ auth()->user()?->email }}</strong>. Click it to finish setting up your account, then you can publish.</p>
    <form method="post" action="{{ route('verification.send') }}">@csrf<button type="submit" class="btn">Resend the link</button></form>
    <p class="muted" style="margin-top:14px;text-align:center"><a href="{{ route('logout') }}" onclick="event.preventDefault();document.getElementById('lo').submit()">Log out</a></p>
    <form id="lo" method="post" action="{{ route('logout') }}" hidden>@csrf</form>
@endsection
