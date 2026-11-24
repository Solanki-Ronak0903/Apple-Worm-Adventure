@extends('layouts.admin')
@section('title', 'Player details')
@section('section', 'PLAYER PROFILE')
@section('content')
<div class="admin-page-head"><div><span class="admin-kicker">ACCOUNT INSPECTION</span><h1>Player <span>profile.</span></h1><p>Review account activity and game progress. Account balances and player progress are not editable from this screen.</p></div><a class="admin-button" href="{{ route('admin.players') }}">← Back to players</a></div>

<div class="admin-profile-head">
    <div class="admin-profile-avatar">{{ strtoupper(substr($player->name, 0, 1)) }}</div>
    <div class="admin-profile-info"><h2>{{ $player->name }}</h2><p>{{ $player->email }}</p><div class="admin-profile-meta"><span class="admin-pill {{ $player->is_admin ? 'info' : 'good' }}">{{ $player->is_admin ? 'Administrator' : 'Player account' }}</span><span class="admin-pill">Player #{{ $player->id }}</span><span class="admin-pill">Joined {{ $player->created_at?->format('d M Y') ?? '—' }}</span></div></div>
</div>

<div class="admin-detail-grid">
    <article class="admin-detail-stat"><small>Wallet balance</small><strong>{{ number_format($player->wallet?->coins ?? 0) }} <span style="color:var(--ad-amber);font-size:12px">◈</span></strong></article>
    <article class="admin-detail-stat"><small>Levels completed</small><strong>{{ number_format($completedLevels) }} <span style="color:var(--ad-teal);font-size:12px">/ 25</span></strong></article>
    <article class="admin-detail-stat"><small>Total stars</small><strong>{{ number_format($totalStars) }} <span style="color:var(--ad-amber);font-size:12px">★</span></strong></article>
    <article class="admin-detail-stat"><small>Sessions played</small><strong>{{ number_format($sessionsPlayed) }}</strong></article>
</div>

<div class="admin-two-col">
    <section class="admin-panel"><div class="admin-panel-head"><div><h2>Level progress</h2><p>{{ $completedLevels }} completed · {{ $unlockedAchievements }} achievements earned</p></div></div><div class="admin-panel-body">
        @forelse($player->progress->sortBy(fn($progress) => $progress->level?->number ?? 999) as $progress)
            <div class="admin-progress-row"><div><strong>{{ $progress->level?->title ?? 'Removed level' }}</strong><small>Level {{ str_pad($progress->level?->number ?? 0, 2, '0', STR_PAD_LEFT) }} · Best score {{ number_format($progress->best_score) }}</small></div><div style="text-align:right"><span class="admin-pill {{ $progress->completed ? 'good' : ($progress->unlocked ? 'info' : '') }}">{{ $progress->completed ? 'Completed' : ($progress->unlocked ? 'Unlocked' : 'Locked') }}</span><div class="admin-stars" style="margin-top:5px">{{ str_repeat('★', (int) $progress->stars) }}{{ str_repeat('☆', max(0, 3 - (int) $progress->stars)) }}</div></div></div>
        @empty<div class="admin-empty">No level progress recorded yet.</div>@endforelse
    </div></section>

    <section class="admin-panel"><div class="admin-panel-head"><div><h2>Achievements</h2><p>Unlocked player achievements</p></div></div><div class="admin-panel-body">
        @php($earned = $player->achievements->whereNotNull('unlocked_at')->sortByDesc('unlocked_at'))
        @forelse($earned as $userAchievement)
            <div class="admin-progress-row"><div><strong>{{ $userAchievement->achievement?->icon ?? '🏆' }} {{ $userAchievement->achievement?->name ?? 'Achievement' }}</strong><small>{{ $userAchievement->achievement?->description ?? 'Achievement details unavailable.' }}</small></div><span class="admin-pill good">Earned</span></div>
        @empty<div class="admin-empty">No achievements unlocked yet.</div>@endforelse
    </div></section>
</div>

<div class="admin-two-col">
    <section class="admin-panel"><div class="admin-panel-head"><div><h2>Recent game sessions</h2><p>Latest ten recorded attempts</p></div></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Level</th><th>Status</th><th>Score</th><th>Moves</th></tr></thead><tbody>
        @forelse($player->gameSessions as $session)<tr><td>{{ $session->level?->title ?? 'Unknown level' }}</td><td><span class="admin-pill {{ $session->status === 'completed' ? 'good' : ($session->status === 'failed' ? 'warn' : 'info') }}">{{ $session->status }}</span></td><td>{{ number_format($session->score) }}</td><td>{{ number_format($session->moves_used) }}</td></tr>@empty<tr><td colspan="4" class="admin-empty">No game sessions yet.</td></tr>@endforelse
    </tbody></table></div></section>

    <section class="admin-panel"><div class="admin-panel-head"><div><h2>Wallet ledger</h2><p>Latest ten wallet transactions · Read only</p></div></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Type</th><th>Amount</th><th>Balance after</th><th>Date</th></tr></thead><tbody>
        @forelse($player->transactions as $transaction)<tr><td>{{ str_replace('_', ' ', $transaction->type) }}<span class="sub">{{ $transaction->description }}</span></td><td style="color:{{ $transaction->amount >= 0 ? 'var(--ad-teal)' : 'var(--ad-red)' }}">{{ $transaction->amount > 0 ? '+' : '' }}{{ number_format($transaction->amount) }}</td><td>{{ number_format($transaction->balance_after) }}</td><td>{{ $transaction->created_at?->format('d M') }}</td></tr>@empty<tr><td colspan="4" class="admin-empty">No wallet transactions yet.</td></tr>@endforelse
    </tbody></table></div></section>
</div>
@endsection
