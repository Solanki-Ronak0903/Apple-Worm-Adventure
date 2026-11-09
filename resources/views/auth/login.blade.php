@extends('layouts.app')
@section('title', 'Log in')
@section('content')
<section class="auth-page auth-page-login">
    <div class="auth-story">
        <div class="auth-kicker"><span class="auth-kicker-icon">✦</span> WELCOME BACK, ADVENTURER</div>
        <h1>Your next little<br><span>big adventure</span><br>is waiting!</h1>
        <p class="auth-intro">Your worm missed you! Log in to collect more apples, solve clever puzzles and keep your star streak going.</p>
        <div class="auth-scene" aria-label="A happy worm with an apple and stars">
            <span class="auth-cloud auth-cloud-one">☁️</span>
            <span class="auth-spark auth-spark-one">✦</span>
            <span class="auth-worm" aria-hidden="true">🐛</span>
            <span class="auth-apple" aria-hidden="true">🍎</span>
            <span class="auth-spark auth-spark-two">✧</span>
            <span class="auth-ground"></span>
            <span class="auth-scene-caption">ONE MORE LEVEL? LET'S GO!</span>
        </div>
        <div class="auth-perks"><span>🍏 Fresh puzzles</span><span>⭐ Earn stars</span><span>🪙 Collect coins</span></div>
    </div>

    <form class="auth-card" method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="auth-card-top"><span class="auth-card-icon">🐛</span><span class="auth-card-tag">PLAYER LOGIN</span></div>
        <h2>Hey, welcome back!</h2>
        <p class="auth-card-subtitle">Enter your details and jump back in.</p>

        @if($errors->any())
            <div class="auth-alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <label class="auth-field" for="email">Email address
            <span class="auth-input-wrap"><span aria-hidden="true">✉️</span><input id="email" name="email" type="email" required autofocus value="{{ old('email') }}" autocomplete="email" placeholder="you@example.com"></span>
            @error('email')<small class="auth-field-error">{{ $message }}</small>@enderror
        </label>
        <label class="auth-field" for="password">Password
            <span class="auth-input-wrap"><span aria-hidden="true">🔒</span><input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password"></span>
            @error('password')<small class="auth-field-error">{{ $message }}</small>@enderror
        </label>
        <label class="auth-remember"><input type="checkbox" name="remember" value="1"> <span>Remember me on this device</span></label>
        <button class="auth-submit" type="submit">Let's play <span aria-hidden="true">➜</span></button>
        <p class="auth-switch">New to the adventure? <a href="{{ route('register') }}">Create an account</a></p>
        <div class="auth-card-foot">🍎 THINK · WIGGLE · WIN 🍎</div>
    </form>
</section>
@endsection
