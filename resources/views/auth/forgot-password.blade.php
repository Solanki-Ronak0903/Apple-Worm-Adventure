@extends('layouts.app')
@section('title', 'Forgot password')
@push('styles')<link rel="stylesheet" href="{{ asset('css/account-security.css') }}">@endpush
@section('content')
<section class="security-auth-wrap"><div class="security-auth-card"><span class="account-kicker">ACCOUNT RECOVERY</span><h1>Reset your <span>password.</span></h1><p>Enter your account email. If an account exists, we'll send a secure reset link.</p>
@if($errors->any())<div class="security-alert error">{{ $errors->first() }}</div>@endif
@if(session('status'))<div class="security-alert">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('password.email') }}" class="account-form">@csrf<label>Email address<input name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></label><button class="account-button" type="submit">Send reset link</button></form><p class="security-back"><a href="{{ route('login') }}">← Back to login</a></p></div></section>
@endsection
