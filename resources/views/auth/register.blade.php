@extends('layouts.app')
@section('title', 'Create account')
@section('content')
<section class="auth-page auth-page-register">
    <div class="auth-story">
        <div class="auth-kicker"><span class="auth-kicker-icon">✦</span> YOUR ADVENTURE STARTS HERE</div>
        <h1>A tiny worm.<br><span>A whole lot</span><br>of fun!</h1>
        <p class="auth-intro">Create your player account, save your progress and start collecting stars on your way to the portal.</p>
        <div class="auth-scene" aria-label="A playful worm, apple and stars">
            <span class="auth-cloud auth-cloud-one">☁️</span>
            <span class="auth-spark auth-spark-one">✦</span>
            <span class="auth-worm" aria-hidden="true">🐛</span>
            <span class="auth-apple" aria-hidden="true">🍏</span>
            <span class="auth-spark auth-spark-two">✧</span>
            <span class="auth-ground"></span>
            <span class="auth-scene-caption">YOUR FIRST LEVEL IS WAITING!</span>
        </div>
        <div class="auth-perks"><span>🎮 25 launch levels</span><span>⭐ Save progress</span><span>🎁 Demo starter coins</span></div>
    </div>

    <form class="auth-card auth-card-register" method="POST" action="{{ route('register.store') }}">
        @csrf
        <div class="auth-card-top"><span class="auth-card-icon">🍎</span><span class="auth-card-tag">NEW PLAYER</span></div>
        <h2>Join the fun!</h2>
        <p class="auth-card-subtitle">Create your account in just a moment.</p>

        @if($errors->any())
            <div class="auth-alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <label class="auth-field" for="name">Player name
            <span class="auth-input-wrap"><span aria-hidden="true">😊</span><input id="name" name="name" type="text" required maxlength="80" value="{{ old('name') }}" autocomplete="name" placeholder="What should we call you?"></span>
            @error('name')<small class="auth-field-error">{{ $message }}</small>@enderror
        </label>
        <label class="auth-field" for="email">Email address
            <span class="auth-input-wrap"><span aria-hidden="true">✉️</span><input id="email" name="email" type="email" required value="{{ old('email') }}" autocomplete="email" placeholder="you@example.com"></span>
            @error('email')<small class="auth-field-error">{{ $message }}</small>@enderror
        </label>
        <label class="auth-field" for="password">Password
            <span class="auth-input-wrap"><span aria-hidden="true">🔒</span><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters"></span>
            @error('password')<small class="auth-field-error">{{ $message }}</small>@enderror
        </label>
        <label class="auth-field" for="password_confirmation">Confirm password
            <span class="auth-input-wrap"><span aria-hidden="true">🔐</span><input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" placeholder="Type it one more time"></span>
        </label>
        <button class="auth-submit" type="submit">Create my account <span aria-hidden="true">➜</span></button>
        <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
        <div class="auth-card-foot">🍎 THINK · WIGGLE · WIN 🍎</div>
    </form>
</section>
@endsection
