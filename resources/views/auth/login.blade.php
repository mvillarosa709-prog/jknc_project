@extends('layouts.guest')

@section('title', 'ORDO sign in')
@section('content')
    <span class="eyebrow">ORDO ACCOUNT</span>
    <h1>Sign in</h1>
    <p class="muted">Sign in to view transmittals associated with your account. Administrators can review and decide submissions.</p>
    <form method="post" action="{{ route('login.store') }}" class="form-stack">
        @csrf
        <label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
        <label class="check-label"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
        <button class="button primary wide" type="submit">Sign in <span>→</span></button>
    </form>
    <a class="back-link guest-back-link" href="{{ route('transmittals.index') }}">← Return to public transmittals</a>
@endsection
