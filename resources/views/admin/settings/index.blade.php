@extends('layouts.admin')
@section('title', 'Admin settings')
@section('section', 'SYSTEM SETTINGS')
@section('content')
<div class="admin-page-head">
    <div>
        <span class="admin-kicker">SYSTEM CONTROL</span>
        <h1>Application settings<span>.</span></h1>
        <p>Control account access and selected game services. Every saved change is recorded in the administrator audit log.</p>
    </div>
    <a class="admin-button admin-button-link" href="{{ route('admin.audit') }}">View audit history →</a>
</div>

<form method="POST" action="{{ route('admin.settings.update') }}" class="admin-settings-form">
    @csrf
    @method('PUT')

    <section class="admin-panel admin-settings-panel">
        <div class="admin-panel-head"><div><h2>Account access</h2><p>Controls that affect new account registration.</p></div><span class="admin-settings-icon">♙</span></div>
        <div class="admin-settings-row">
            <div><strong>Allow new registrations</strong><p>When disabled, visitors cannot open the registration page or submit a new account. Existing players can still sign in.</p></div>
            <label class="admin-switch"><input type="checkbox" name="allow_registration" value="1" @checked($settings['allow_registration'])><span></span><b data-enabled-label>{{ $settings['allow_registration'] ? 'Enabled' : 'Disabled' }}</b></label>
        </div>
        <div class="admin-settings-row">
            <div><strong>Require verified email before playing</strong><p>When enabled, players must verify their email address before starting levels, claiming rewarded actions or opening the wallet and leaderboard. Verification links are sent by email.</p></div>
            <label class="admin-switch"><input type="checkbox" name="require_email_verification" value="1" @checked($settings['require_email_verification'])><span></span><b data-enabled-label>{{ $settings['require_email_verification'] ? 'Enabled' : 'Disabled' }}</b></label>
        </div>
        <div class="admin-settings-row">
            <div><strong>Starting coins for new players</strong><p>Initial wallet balance assigned to newly registered accounts. Existing wallet balances are never changed.</p></div>
            <div class="admin-setting-number"><input class="admin-field" type="number" name="new_player_starting_coins" min="0" max="5000" step="1" value="{{ old('new_player_starting_coins', $settings['new_player_starting_coins']) }}" required><span>coins</span></div>
        </div>
    </section>

    <section class="admin-panel admin-settings-panel">
        <div class="admin-panel-head"><div><h2>Game services</h2><p>Enable or disable player-facing features without changing player history.</p></div><span class="admin-settings-icon">🎮</span></div>
        <div class="admin-settings-row">
            <div><strong>Public leaderboard</strong><p>When disabled, the leaderboard page is unavailable to players. Existing scores and level progress remain stored.</p></div>
            <label class="admin-switch"><input type="checkbox" name="leaderboard_enabled" value="1" @checked($settings['leaderboard_enabled'])><span></span><b data-enabled-label>{{ $settings['leaderboard_enabled'] ? 'Enabled' : 'Disabled' }}</b></label>
        </div>
        <div class="admin-settings-row">
            <div><strong>Rewarded actions</strong><p>Controls development-mock rewarded actions such as revive, extra moves, hints and boosters. Normal level-completion rewards are not affected.</p></div>
            <label class="admin-switch"><input type="checkbox" name="rewarded_actions_enabled" value="1" @checked($settings['rewarded_actions_enabled'])><span></span><b data-enabled-label>{{ $settings['rewarded_actions_enabled'] ? 'Enabled' : 'Disabled' }}</b></label>
        </div>
    </section>

    <div class="admin-settings-actions">
        <p>Settings take effect immediately after saving. Sensitive credentials and environment secrets are not editable here.</p>
        <button type="submit" class="admin-button">Save application settings</button>
    </div>
</form>

<div class="admin-level-note"><span>ⓘ</span><div><strong>Protected administration</strong><p>Only authenticated administrators can update these settings. Each change records the previous and new values, administrator account, timestamp and request metadata in the audit log.</p></div></div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.admin-switch input').forEach((input) => {
        input.addEventListener('change', () => {
            const label = input.parentElement.querySelector('[data-enabled-label]');
            if (label) label.textContent = input.checked ? 'Enabled' : 'Disabled';
        });
    });
</script>
@endpush
