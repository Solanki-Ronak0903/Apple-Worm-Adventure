@extends('layouts.admin')
@section('title', 'Admin overview')
@section('section', 'OVERVIEW')
@section('content')
<div class="admin-page-head">
    <div><span class="admin-kicker">GAME OPERATIONS</span><h1>Good to see you, <span>{{ str(auth()->user()->name)->before(' ') }}.</span></h1><p>Your game's activity, player growth and reward health at a glance.</p></div>
    <div class="admin-date-chip">{{ now()->format('l, d M Y') }}</div>
</div>

<div class="admin-stat-grid">
    <article class="admin-stat-card" style="--accent:var(--ad-teal);--glow:var(--ad-teal)"><div class="admin-stat-top"><span>Registered players</span><span class="admin-stat-icon">♙</span></div><strong>{{ number_format($players) }}</strong><div class="admin-stat-foot"><b>+{{ number_format($newPlayers) }}</b> joined in the last 7 days</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-blue);--glow:var(--ad-blue)"><div class="admin-stat-top"><span>Active players</span><span class="admin-stat-icon">◉</span></div><strong>{{ number_format($activePlayers) }}</strong><div class="admin-stat-foot">Played at least once in the last 7 days</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-lime);--glow:var(--ad-lime)"><div class="admin-stat-top"><span>Published levels</span><span class="admin-stat-icon">▦</span></div><strong>{{ number_format($publishedLevels) }}</strong><div class="admin-stat-foot">Available in the current release</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-amber);--glow:var(--ad-amber)"><div class="admin-stat-top"><span>Completed sessions</span><span class="admin-stat-icon">✓</span></div><strong>{{ number_format($completedSessions) }}</strong><div class="admin-stat-foot">{{ $completionRate }}% of {{ number_format($totalSessions) }} game sessions</div></article>
</div>

<div class="admin-stat-grid">
    <article class="admin-stat-card" style="--accent:var(--ad-teal)"><div class="admin-stat-top"><span>Coins in wallets</span><span class="admin-stat-icon">◈</span></div><strong>{{ number_format($coinsInCirculation) }}</strong><div class="admin-stat-foot">Current player and admin wallet balances</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-blue)"><div class="admin-stat-top"><span>Reward claims</span><span class="admin-stat-icon">🎁</span></div><strong>{{ number_format($totalClaims) }}</strong><div class="admin-stat-foot">{{ number_format($pendingClaims) }} currently pending</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-lime)"><div class="admin-stat-top"><span>Wallet transactions</span><span class="admin-stat-icon">⇄</span></div><strong>{{ number_format($transactionCount) }}</strong><div class="admin-stat-foot">Ledger records across all accounts</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-amber)"><div class="admin-stat-top"><span>Game sessions</span><span class="admin-stat-icon">⌁</span></div><strong>{{ number_format($totalSessions) }}</strong><div class="admin-stat-foot">All recorded attempts</div></article>
</div>

<div class="admin-grid-main">
    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>Weekly game activity</h2><p>Sessions created over the last seven days</p></div><span class="admin-pill info">7 DAYS</span></div>
        <div class="admin-panel-body">
            @php($maxActivity = max(1, (int) $weeklyActivity->max('count')))
            <div class="admin-chart" aria-label="Daily game sessions chart">
                @foreach($weeklyActivity as $day)
                    <div class="admin-chart-col"><b>{{ number_format($day['count']) }}</b><div class="admin-chart-bar-wrap"><div class="admin-chart-bar" title="{{ $day['date'] }}: {{ $day['count'] }} sessions" style="height:{{ max(4, ($day['count'] / $maxActivity) * 100) }}%"></div></div><small>{{ $day['label'] }}</small></div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>Most completed levels</h2><p>Based on player completion records</p></div><span class="admin-pill good">TOP 5</span></div>
        <div class="admin-panel-body"><div class="admin-mini-list">
            @forelse($topLevels as $index => $level)
                <div class="admin-mini-row"><span class="admin-mini-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span><div><div class="admin-mini-title">{{ $level->title }}</div><div class="admin-mini-sub">Level {{ str_pad($level->number, 2, '0', STR_PAD_LEFT) }} · {{ ucfirst($level->difficulty) }}</div></div><strong class="admin-mini-value">{{ number_format($level->completed_players_count) }}</strong></div>
            @empty
                <div class="admin-empty">No published levels found.</div>
            @endforelse
        </div></div>
    </section>
</div>

<div class="admin-panel" style="margin-top:15px">
    <div class="admin-panel-head"><div><h2>Recent game activity</h2><p>Latest sessions across player accounts</p></div><a class="admin-link" href="{{ route('admin.players') }}">Browse players →</a></div>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Player</th><th>Level</th><th>Score</th><th>Moves</th><th>Status</th><th>Started</th></tr></thead><tbody>
        @forelse($recentSessions as $session)
            <tr><td><a class="admin-player-name" href="{{ route('admin.players.show', $session->user) }}">{{ $session->user?->name ?? 'Deleted account' }}</a><span class="sub">{{ $session->user?->email ?? '—' }}</span></td><td>{{ $session->level?->title ?? 'Unknown level' }}</td><td>{{ number_format($session->score) }}</td><td>{{ number_format($session->moves_used) }}</td><td><span class="admin-pill {{ $session->status === 'completed' ? 'good' : ($session->status === 'failed' ? 'warn' : 'info') }}">{{ $session->status }}</span></td><td>{{ $session->created_at?->diffForHumans() ?? '—' }}</td></tr>
        @empty
            <tr><td colspan="6" class="admin-empty">No game sessions recorded yet.</td></tr>
        @endforelse
    </tbody></table></div>
</div>

<div class="admin-grid-main">
    <section class="admin-panel"><div class="admin-panel-head"><div><h2>Recent reward claims</h2><p>Latest rewarded actions</p></div><a class="admin-link" href="{{ route('admin.economy') }}">Open economy →</a></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Player</th><th>Action</th><th>Status</th></tr></thead><tbody>
            @forelse($recentClaims as $claim)<tr><td>{{ $claim->user?->name ?? 'Deleted account' }}</td><td>{{ str_replace('_', ' ', $claim->action) }}</td><td><span class="admin-pill {{ $claim->status === 'granted' ? 'good' : 'warn' }}">{{ $claim->status }}</span></td></tr>@empty<tr><td colspan="3" class="admin-empty">No reward claims yet.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    <section class="admin-panel"><div class="admin-panel-head"><div><h2>Quick actions</h2><p>Common admin destinations</p></div></div><div class="admin-panel-body"><div class="admin-mini-list">
        <a class="admin-mini-row" href="{{ route('admin.players') }}"><span class="admin-mini-number">♙</span><div><div class="admin-mini-title">Open player directory</div><div class="admin-mini-sub">Search accounts and inspect progress</div></div><span class="admin-mini-value">→</span></a>
        <a class="admin-mini-row" href="{{ route('admin.levels') }}"><span class="admin-mini-number">▧</span><div><div class="admin-mini-title">Manage puzzle levels</div><div class="admin-mini-sub">Review maps, hints and publication status</div></div><span class="admin-mini-value">→</span></a>
        <a class="admin-mini-row" href="{{ route('admin.economy') }}"><span class="admin-mini-number">◈</span><div><div class="admin-mini-title">Review reward economy</div><div class="admin-mini-sub">Inspect claims and provider statuses</div></div><span class="admin-mini-value">→</span></a>
        <a class="admin-mini-row" href="{{ route('levels') }}"><span class="admin-mini-number">▦</span><div><div class="admin-mini-title">Preview player level map</div><div class="admin-mini-sub">Open the live player experience</div></div><span class="admin-mini-value">↗</span></a>
    </div></div></section>
</div>
@endsection
