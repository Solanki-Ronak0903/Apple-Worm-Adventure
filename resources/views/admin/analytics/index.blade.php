@extends('layouts.admin')
@section('title', 'Game analytics')
@section('section', 'GAME ANALYTICS')
@section('content')
<div class="admin-page-head analytics-page-head">
    <div>
        <span class="admin-kicker">PLAYER INSIGHTS & GAME HEALTH</span>
        <h1>Game analytics<span>.</span></h1>
        <p>Understand player activity, puzzle completion, level difficulty and the in-game economy using recorded player events.</p>
    </div>
    <form class="analytics-period-form" method="GET" action="{{ route('admin.analytics') }}">
        <label for="analyticsPeriod">REPORT PERIOD</label>
        <select class="admin-field admin-select" id="analyticsPeriod" name="period" onchange="this.form.submit()">
            <option value="7" @selected($period === 7)>Last 7 days</option>
            <option value="30" @selected($period === 30)>Last 30 days</option>
            <option value="90" @selected($period === 90)>Last 90 days</option>
        </select>
    </form>
</div>

<div class="analytics-range-note">
    <span class="analytics-range-dot"></span>
    Showing player activity from <strong>{{ $startDate->format('d M Y') }}</strong> through <strong>{{ $endDate->format('d M Y') }}</strong>. Administrator accounts are excluded.
</div>

<div class="admin-stat-grid analytics-stat-grid">
    <article class="admin-stat-card" style="--accent:var(--ad-teal)"><div class="admin-stat-top"><span>Game sessions</span><span class="admin-stat-icon">⌁</span></div><strong>{{ number_format($metrics['sessions']) }}</strong><div class="admin-stat-foot">All recorded attempts in this period</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-blue)"><div class="admin-stat-top"><span>Active players</span><span class="admin-stat-icon">◉</span></div><strong>{{ number_format($metrics['active_players']) }}</strong><div class="admin-stat-foot">Unique players who started a session</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-lime)"><div class="admin-stat-top"><span>Completion rate</span><span class="admin-stat-icon">✓</span></div><strong>{{ number_format($metrics['completion_rate'], 1) }}<small>%</small></strong><div class="admin-stat-foot">{{ number_format($metrics['completed']) }} completed sessions</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-amber)"><div class="admin-stat-top"><span>New players</span><span class="admin-stat-icon">♙</span></div><strong>{{ number_format($metrics['new_players']) }}</strong><div class="admin-stat-foot">Accounts registered in this period</div></article>
</div>

<div class="admin-stat-grid analytics-stat-grid analytics-stat-grid-secondary">
    <article class="admin-stat-card" style="--accent:var(--ad-blue)"><div class="admin-stat-top"><span>Returning players</span><span class="admin-stat-icon">↻</span></div><strong>{{ number_format($metrics['returning_players']) }}</strong><div class="admin-stat-foot">Players with 2 or more sessions</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-teal)"><div class="admin-stat-top"><span>Average moves</span><span class="admin-stat-icon">↗</span></div><strong>{{ $metrics['average_moves'] !== null ? number_format($metrics['average_moves'], 1) : '—' }}</strong><div class="admin-stat-foot">Per completed session with moves recorded</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-rose, #e78b91)"><div class="admin-stat-top"><span>Failed sessions</span><span class="admin-stat-icon">!</span></div><strong>{{ number_format($metrics['failed']) }}</strong><div class="admin-stat-foot">{{ number_format($metrics['failure_rate'], 1) }}% of recorded sessions</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-amber)"><div class="admin-stat-top"><span>Hints used</span><span class="admin-stat-icon">?</span></div><strong>{{ number_format($metrics['hints_used']) }}</strong><div class="admin-stat-foot">Across sessions in this period</div></article>
</div>

<div class="admin-grid-main analytics-primary-grid">
    <section class="admin-panel analytics-activity-panel">
        <div class="admin-panel-head"><div><h2>Session activity</h2><p>Daily starts, completed attempts and failed attempts</p></div><span class="admin-pill info">{{ $period }} DAYS</span></div>
        <div class="admin-panel-body">
            <div class="analytics-legend"><span><i class="legend-sessions"></i> Sessions</span><span><i class="legend-completed"></i> Completed</span><span><i class="legend-failed"></i> Failed</span></div>
            <div class="analytics-chart" role="img" aria-label="Daily game session counts for the selected report period">
                @foreach($dailyActivity as $day)
                    @php($totalHeight = $day['sessions'] > 0 ? max(3, ($day['sessions'] / $maxDailySessions) * 100) : 0)
                    @php($completedHeight = $day['sessions'] > 0 ? ($day['completed'] / $day['sessions']) * 100 : 0)
                    @php($failedHeight = $day['sessions'] > 0 ? ($day['failed'] / $day['sessions']) * 100 : 0)
                    <div class="analytics-chart-column" title="{{ $day['date'] }}: {{ $day['sessions'] }} sessions, {{ $day['completed'] }} completed, {{ $day['failed'] }} failed">
                        <strong>{{ $day['sessions'] ?: '' }}</strong>
                        <div class="analytics-chart-track">
                            <div class="analytics-chart-bar" style="height:{{ $totalHeight }}%">
                                <span class="analytics-bar-completed" style="height:{{ $completedHeight }}%"></span>
                                <span class="analytics-bar-failed" style="height:{{ $failedHeight }}%"></span>
                            </div>
                        </div>
                        <small>{{ $day['show_label'] ? $day['label'] : '' }}</small>
                    </div>
                @endforeach
            </div>
            <div class="analytics-chart-foot"><span>{{ number_format($metrics['completed']) }} completed</span><span>{{ number_format($metrics['active_sessions']) }} currently active</span><span>{{ number_format($metrics['failed']) }} failed</span></div>
        </div>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>Player engagement</h2><p>How players are using game assistance</p></div><span class="admin-pill info">PERIOD</span></div>
        <div class="admin-panel-body analytics-engagement-list">
            <div class="analytics-metric-row"><span>Returning players <small>2+ sessions in selected period</small></span><strong>{{ number_format($metrics['returning_players']) }}</strong></div>
            <div class="analytics-metric-row"><span>Revives used <small>Rewarded revives recorded</small></span><strong>{{ number_format($metrics['revives_used']) }}</strong></div>
            <div class="analytics-metric-row"><span>Boosters used <small>Boosters recorded in sessions</small></span><strong>{{ number_format($metrics['boosters_used']) }}</strong></div>
            <div class="analytics-metric-row"><span>Claims granted <small>Reward claims successfully granted</small></span><strong>{{ number_format($metrics['granted_claims']) }}</strong></div>
            <div class="analytics-metric-row"><span>Claims pending <small>Awaiting provider/result handling</small></span><strong>{{ number_format($metrics['pending_claims']) }}</strong></div>
            <div class="analytics-metric-row"><span>Other claim states <small>Failed or other non-pending states</small></span><strong>{{ number_format($metrics['other_claims']) }}</strong></div>
        </div>
    </section>
</div>

<div class="admin-grid-main analytics-secondary-grid">
    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>Most played levels</h2><p>Ranked by session starts in this period</p></div><a class="admin-link" href="{{ route('admin.levels') }}">Manage levels →</a></div>
        <div class="admin-panel-body"><div class="admin-mini-list">
            @forelse($topLevels as $index => $level)
                <div class="admin-mini-row"><span class="admin-mini-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span><div><div class="admin-mini-title">{{ $level['title'] }}</div><div class="admin-mini-sub">Level {{ str_pad($level['number'], 2, '0', STR_PAD_LEFT) }} · {{ $level['world'] }}</div></div><strong class="admin-mini-value">{{ number_format($level['sessions']) }}</strong></div>
            @empty<div class="admin-empty">No player sessions recorded in this period.</div>@endforelse
        </div></div>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>Levels to review</h2><p>At least 3 sessions, ordered by completion rate</p></div><span class="admin-pill warn">QA SIGNAL</span></div>
        <div class="admin-panel-body"><div class="admin-mini-list">
            @forelse($levelsToReview as $level)
                <div class="analytics-review-row"><div><div class="admin-mini-title">{{ str_pad($level['number'], 2, '0', STR_PAD_LEFT) }} · {{ $level['title'] }}</div><div class="admin-mini-sub">{{ $level['sessions'] }} attempts · {{ $level['failed'] }} failed</div></div><strong class="analytics-rate {{ $level['completion_rate'] < 40 ? 'is-low' : 'is-mid' }}">{{ number_format($level['completion_rate'], 1) }}%</strong></div>
            @empty<div class="admin-empty">Not enough attempts to flag levels yet. A level needs at least 3 sessions.</div>@endforelse
        </div></div>
    </section>
</div>

<section class="admin-panel analytics-world-panel">
    <div class="admin-panel-head"><div><h2>World performance</h2><p>Combined session outcomes for published levels in each world</p></div><span class="admin-pill info">5 WORLDS</span></div>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>World</th><th>Levels</th><th>Sessions</th><th>Completed</th><th>Completion rate</th></tr></thead><tbody>
        @forelse($worldPerformance as $world)
            <tr><td><strong>{{ $world['world'] }}</strong></td><td>{{ $world['levels'] }}</td><td>{{ number_format($world['sessions']) }}</td><td>{{ number_format($world['completed']) }}</td><td><div class="analytics-inline-rate"><span>{{ number_format($world['completion_rate'], 1) }}%</span><i><b style="width:{{ min(100, $world['completion_rate']) }}%"></b></i></div></td></tr>
        @empty<tr><td colspan="5" class="admin-empty">No published worlds found.</td></tr>@endforelse
    </tbody></table></div>
</section>

<section class="admin-panel analytics-level-table-panel">
    <div class="admin-panel-head"><div><h2>Level performance detail</h2><p>Session outcomes and average moves for every published level</p></div><span class="admin-pill info">{{ $levelPerformance->count() }} LEVELS</span></div>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Level</th><th>World</th><th>Difficulty</th><th>Sessions</th><th>Completed</th><th>Failed</th><th>Completion</th><th>Avg. moves</th></tr></thead><tbody>
        @foreach($levelPerformance as $level)
            <tr><td><strong>{{ str_pad($level['number'], 2, '0', STR_PAD_LEFT) }} · {{ $level['title'] }}</strong></td><td>{{ $level['world'] }}</td><td><span class="admin-pill info">{{ ucfirst($level['difficulty']) }}</span></td><td>{{ number_format($level['sessions']) }}</td><td>{{ number_format($level['completed']) }}</td><td>{{ number_format($level['failed']) }}</td><td>{{ number_format($level['completion_rate'], 1) }}%</td><td>{{ $level['average_moves'] !== null ? number_format($level['average_moves'], 1) : '—' }}</td></tr>
        @endforeach
    </tbody></table></div>
</section>

<div class="admin-grid-main analytics-secondary-grid">
    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>Most active players</h2><p>Ranked by sessions in this period</p></div><a class="admin-link" href="{{ route('admin.players') }}">Player directory →</a></div>
        <div class="admin-panel-body"><div class="admin-mini-list">
            @forelse($topPlayers as $index => $player)
                <div class="admin-mini-row"><span class="admin-mini-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span><div class="admin-mini-title">{{ $player['name'] }}</div><strong class="admin-mini-value">{{ number_format($player['sessions']) }} sessions</strong></div>
            @empty<div class="admin-empty">No active players in this period.</div>@endforelse
        </div></div>
    </section>
    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>Wallet movement</h2><p>Signed ledger entries in this period</p></div><a class="admin-link" href="{{ route('admin.economy') }}">Open economy →</a></div>
        <div class="admin-panel-body analytics-wallet-grid">
            <div><small>COINS CREDITED</small><strong class="is-credit">+{{ number_format($metrics['coins_credited']) }}</strong></div>
            <div><small>COINS DEBITED</small><strong class="is-debit">−{{ number_format($metrics['coins_debited']) }}</strong></div>
            <p>Values are calculated from signed wallet transaction amounts, excluding administrator accounts.</p>
        </div>
    </section>
</div>

<div class="admin-grid-main analytics-secondary-grid">
    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>Rewarded action breakdown</h2><p>Reward claims grouped by action in this period</p></div><a class="admin-link" href="{{ route('admin.economy') }}">Review claims →</a></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Action</th><th>Claims</th></tr></thead><tbody>
            @forelse($claimActions as $action)
                <tr><td><strong>{{ str_replace('_', ' ', ucfirst($action->action)) }}</strong></td><td>{{ number_format($action->total) }}</td></tr>
            @empty<tr><td colspan="2" class="admin-empty">No reward claims in this period.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>Wallet ledger categories</h2><p>Transaction entries grouped by recorded type</p></div><a class="admin-link" href="{{ route('admin.economy') }}">Open economy →</a></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Transaction type</th><th>Entries</th><th>Net amount</th></tr></thead><tbody>
            @forelse($transactionTypes as $transactionType)
                <tr><td><strong>{{ str_replace('_', ' ', ucfirst($transactionType->type)) }}</strong></td><td>{{ number_format($transactionType->entries) }}</td><td>{{ number_format($transactionType->amount_total) }}</td></tr>
            @empty<tr><td colspan="3" class="admin-empty">No wallet ledger entries in this period.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>

<div class="analytics-footnote"><strong>How to read this report:</strong> session metrics use the session creation date. Completion rate is completed sessions divided by all sessions in the selected period. “Returning players” means players with at least two sessions in that period; it is not a cohort-retention calculation. “Levels to review” is a QA signal, not an automatic difficulty verdict. Achievement unlock total is all-time: {{ number_format($totalUnlocks) }} unlocks across {{ number_format($totalAchievements) }} definitions.</div>
@endsection
