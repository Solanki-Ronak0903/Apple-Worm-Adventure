@extends('layouts.admin')
@section('title', 'Manage achievement')
@section('section', 'ACHIEVEMENT DETAILS')
@section('content')
<a class="admin-inline-back" href="{{ route('admin.achievements') }}">← Back to achievements</a>
<div class="admin-page-head"><div><span class="admin-kicker">ACHIEVEMENT MANAGEMENT</span><h1>{{ $achievement->name }} <span>.</span></h1><p>{{ $achievement->code }} · {{ $achievement->description }}</p></div><span class="admin-pill {{ $achievement->is_active ? 'good' : 'warn' }}">{{ $achievement->is_active ? 'Active' : 'Inactive' }}</span></div>
<div class="admin-profile-head admin-achievement-profile"><span class="admin-achievement-large-icon">{{ $achievement->icon }}</span><div class="admin-profile-info"><h2>{{ $achievement->name }}</h2><p>{{ $metricLabel }} · Target {{ number_format($target) }} · {{ number_format($rewardCoins) }} coin reward</p><div class="admin-profile-meta"><span class="admin-pill info">{{ $achievement->code }}</span><span class="admin-pill {{ $achievement->is_active ? 'good' : 'warn' }}">{{ $achievement->is_active ? 'Available for new unlocks' : 'Not available for new unlocks' }}</span></div></div></div>
<div class="admin-detail-grid admin-achievement-detail-stats">
    <article class="admin-detail-stat"><small>Players unlocked</small><strong>{{ number_format($achievement->unlocks_count) }}</strong></article>
    <article class="admin-detail-stat"><small>Players tracking</small><strong>{{ number_format($achievement->tracked_players_count) }}</strong></article>
    <article class="admin-detail-stat"><small>Unlock target</small><strong>{{ number_format($target) }}</strong></article>
    <article class="admin-detail-stat"><small>Coin reward</small><strong>{{ number_format($rewardCoins) }} <small>coins</small></strong></article>
</div>

@include('admin.achievements._form', ['creating' => false, 'formAction' => route('admin.achievements.update', $achievement), 'method' => 'PUT', 'formTitle' => 'Edit achievement', 'submitLabel' => 'Save changes', 'currentMetric' => $metric])

<section class="admin-panel admin-achievement-status-panel">
    <div class="admin-panel-head"><div><h2>Availability</h2><p>Deactivate an achievement to stop future progress. Existing earned badges stay visible to their owners.</p></div></div>
    <div class="admin-achievement-status-row"><div><strong>{{ $achievement->is_active ? 'Achievement is active' : 'Achievement is inactive' }}</strong><small>{{ $achievement->is_active ? 'Players can continue progressing toward this goal.' : 'Players cannot gain new progress toward this goal.' }}</small></div>
        <form method="POST" action="{{ route('admin.achievements.status', $achievement) }}">@csrf @method('PATCH')<button class="admin-button {{ $achievement->is_active ? 'admin-button-danger' : '' }}" type="submit">{{ $achievement->is_active ? 'Deactivate' : 'Activate' }}</button></form>
    </div>
</section>

<section class="admin-panel admin-achievement-unlocks">
    <div class="admin-panel-head"><div><h2>Recent unlocks</h2><p>Latest players who earned this achievement.</p></div></div>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Player</th><th>Progress</th><th>Unlocked</th></tr></thead><tbody>
        @forelse($recentUnlocks as $record)
            <tr><td>@if($record->user)<a class="admin-player-name" href="{{ route('admin.players.show', $record->user) }}">{{ $record->user->name }}</a><span class="sub">{{ $record->user->email }}</span>@else Deleted account @endif</td><td>{{ collect((array) $record->progress)->except('reward_coins')->map(fn ($value, $key) => str_replace('_', ' ', $key) . ': ' . $value)->implode(' · ') ?: 'Completed' }}</td><td>{{ $record->unlocked_at?->format('d M Y H:i') ?? '—' }}</td></tr>
        @empty
            <tr><td colspan="3" class="admin-empty">No players have unlocked this achievement yet.</td></tr>
        @endforelse
    </tbody></table></div>
</section>
@endsection
