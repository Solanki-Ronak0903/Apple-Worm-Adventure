@extends('layouts.app')
@section('title', 'Account security')
@push('styles')<link rel="stylesheet" href="{{ asset('css/account-security.css') }}">@endpush
@section('content')
<section class="account-shell">
    <header class="account-heading">
        <span class="account-kicker">PLAYER ACCOUNT</span>
        <h1>Account &amp; <span>security.</span></h1>
        <p>Keep your profile up to date and protect your adventure.</p>
    </header>

    <div class="account-grid">
        <section class="account-card">
            <div class="account-card-heading"><span class="account-icon">♙</span><div><h2>Profile details</h2><p>Update your player name and email address.</p></div></div>
            <form method="POST" action="{{ route('account.profile.update') }}" class="account-form">
                @csrf @method('PATCH')
                <label>Player name<input name="name" value="{{ old('name', auth()->user()->name) }}" maxlength="80" required autocomplete="name"></label>
                <label>Email address<input name="email" type="email" value="{{ old('email', auth()->user()->email) }}" maxlength="190" required autocomplete="email"></label>
                <label>Confirm with current password<input name="current_password" type="password" required autocomplete="current-password"></label>
                <button class="account-button" type="submit">Save profile</button>
            </form>
            <div class="account-verify-status">
                <span class="account-status-dot {{ auth()->user()->hasVerifiedEmail() ? 'is-good' : 'is-pending' }}"></span>
                <div><strong>{{ auth()->user()->hasVerifiedEmail() ? 'Email verified' : 'Email not verified' }}</strong><p>{{ auth()->user()->hasVerifiedEmail() ? 'Your email address is verified.' : 'Verify your email to protect account recovery and meet the game access policy if enabled.' }}</p></div>
                @unless(auth()->user()->hasVerifiedEmail())<form method="POST" action="{{ route('verification.send') }}">@csrf<button class="account-link-button" type="submit">Resend link</button></form>@endunless
            </div>
        </section>

        <section class="account-card">
            <div class="account-card-heading"><span class="account-icon">♢</span><div><h2>Change password</h2><p>Use a unique password you don't use on other websites.</p></div></div>
            <form method="POST" action="{{ route('account.password.update') }}" class="account-form">
                @csrf @method('PUT')
                <label>Current password<input name="current_password" type="password" required autocomplete="current-password"></label>
                <label>New password<input name="password" type="password" required minlength="12" autocomplete="new-password"><small>At least 12 characters, including uppercase and lowercase letters, a number and a symbol.</small></label>
                <label>Confirm new password<input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label>
                <button class="account-button" type="submit">Update password</button>
            </form>
        </section>

        <section class="account-card account-card-wide">
            <div class="account-card-heading"><span class="account-icon">⌁</span><div><h2>Device sessions</h2><p>Sign out other remembered devices if you no longer recognize them.</p></div></div>
            <form method="POST" action="{{ route('account.sessions.destroy') }}" class="account-session-form">
                @csrf
                <label>Confirm with your current password<input name="current_password" type="password" required autocomplete="current-password"></label>
                <button class="account-button account-button-secondary" type="submit">Sign out other devices</button>
            </form>
            <p class="account-note">After this action, other remembered sessions will be asked to sign in again. Your current session stays active.</p>
        </section>

        <section class="account-card account-card-wide">
            <div class="account-card-heading"><span class="account-icon">◷</span><div><h2>Recent security activity</h2><p>Recent sign-ins and account security changes for this account.</p></div></div>
            @if($securityEvents->isEmpty())
                <p class="account-empty">No security events have been recorded yet.</p>
            @else
                <div class="account-event-list">
                    @foreach($securityEvents as $event)
                        <article class="account-event"><div><strong>{{ str($event->event)->replace('.', ' ')->replace('_', ' ')->title() }}</strong><small>{{ $event->ip_address ?: 'IP unavailable' }}</small></div><time datetime="{{ $event->created_at?->toIso8601String() }}">{{ $event->created_at?->format('d M Y, H:i') }}</time></article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</section>
@endsection
