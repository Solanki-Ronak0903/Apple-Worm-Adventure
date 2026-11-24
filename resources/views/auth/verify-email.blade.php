@extends('layouts.app')
@section('title', 'Verify email address')
@push('styles')<link rel="stylesheet" href="{{ asset('css/account-security.css') }}">@endpush
@section('content')
<section class="security-auth-wrap"><div class="security-auth-card"><span class="account-kicker">SECURE YOUR ADVENTURE</span><h1>Verify your <span>email.</span></h1><p>We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Open that link to confirm you own this address.</p>
@if(session('status'))<div class="security-alert">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('verification.send') }}">@csrf<button class="account-button" type="submit">Send a fresh verification link</button></form><p class="security-back"><a href="{{ route('account') }}">Manage account</a></p></div></section>
@endsection
