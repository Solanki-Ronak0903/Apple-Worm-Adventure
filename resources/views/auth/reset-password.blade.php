@extends('layouts.app')
@section('title', 'Choose a new password')
@push('styles')<link rel="stylesheet" href="{{ asset('css/account-security.css') }}">@endpush
@section('content')
<section class="security-auth-wrap"><div class="security-auth-card"><span class="account-kicker">ACCOUNT RECOVERY</span><h1>Choose a new <span>password.</span></h1><p>Your new password must be at least 12 characters and include uppercase and lowercase letters, a number and a symbol.</p>
@if($errors->any())<div class="security-alert error">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.update') }}" class="account-form">@csrf<input type="hidden" name="token" value="{{ $token }}"><label>Email address<input name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email"></label><label>New password<input name="password" type="password" required minlength="12" autocomplete="new-password"></label><label>Confirm new password<input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label><button class="account-button" type="submit">Reset password</button></form><p class="security-back"><a href="{{ route('login') }}">← Back to login</a></p></div></section>
@endsection
