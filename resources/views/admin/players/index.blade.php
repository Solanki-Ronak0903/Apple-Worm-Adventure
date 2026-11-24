@extends('layouts.admin')
@section('title', 'Player management')
@section('section', 'PLAYERS')
@section('content')
<div class="admin-page-head"><div><span class="admin-kicker">COMMUNITY MANAGEMENT</span><h1>Player <span>directory.</span></h1><p>Find player accounts and review their activity, progress, achievements and wallet balance. This first release is read-only to protect player data.</p></div><div class="admin-date-chip">{{ number_format($totalPlayers) }} players · {{ number_format($adminsCount) }} admins</div></div>

<div class="admin-stat-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));max-width:560px">
    <article class="admin-stat-card"><div class="admin-stat-top"><span>Player accounts</span><span class="admin-stat-icon">♙</span></div><strong>{{ number_format($totalPlayers) }}</strong><div class="admin-stat-foot">Non-admin accounts</div></article>
    <article class="admin-stat-card"><div class="admin-stat-top"><span>Administrators</span><span class="admin-stat-icon">✦</span></div><strong>{{ number_format($adminsCount) }}</strong><div class="admin-stat-foot">Privileged accounts</div></article>
</div>

<section class="admin-panel">
    <form class="admin-toolbar" method="GET" action="{{ route('admin.players') }}">
        <div class="admin-search-form">
            <input class="admin-field admin-search" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search name, email or player ID…" aria-label="Search players">
            <select class="admin-field admin-select" name="role" aria-label="Filter account role"><option value="player" @selected($role === 'player')>Players</option><option value="all" @selected($role === 'all')>All accounts</option><option value="admin" @selected($role === 'admin')>Admins</option></select>
            <select class="admin-field admin-select" name="sort" aria-label="Sort players"><option value="newest" @selected($sort === 'newest')>Newest first</option><option value="oldest" @selected($sort === 'oldest')>Oldest first</option><option value="most_active" @selected($sort === 'most_active')>Most active</option></select>
            <button class="admin-button" type="submit">Apply filters</button>
            <a class="admin-reset-link" href="{{ route('admin.players') }}">Reset</a>
        </div>
    </form>
    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Player</th><th>Role</th><th>Wallet</th><th>Sessions</th><th>Levels cleared</th><th>Achievements</th><th>Joined</th><th></th></tr></thead><tbody>
        @forelse($players as $player)
            <tr>
                <td><div class="admin-player-cell"><span class="admin-player-dot">{{ strtoupper(substr($player->name, 0, 1)) }}</span><div><a class="admin-player-name" href="{{ route('admin.players.show', $player) }}">{{ $player->name }}</a><span class="sub">{{ $player->email }} · #{{ $player->id }}</span></div></div></td>
                <td><span class="admin-pill {{ $player->is_admin ? 'info' : 'good' }}">{{ $player->is_admin ? 'Admin' : 'Player' }}</span></td>
                <td>{{ number_format($player->wallet?->coins ?? 0) }} <span class="sub">coins</span></td>
                <td>{{ number_format($player->game_sessions_count) }}</td>
                <td>{{ number_format($player->completed_levels_count) }} / 25</td>
                <td>{{ number_format($player->unlocked_achievements_count) }}</td>
                <td>{{ $player->created_at?->format('d M Y') ?? '—' }}</td>
                <td><a class="admin-link" href="{{ route('admin.players.show', $player) }}">View →</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="admin-empty">No accounts match these filters.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="admin-table-footer"><span>Showing {{ $players->firstItem() ?? 0 }}–{{ $players->lastItem() ?? 0 }} of {{ number_format($players->total()) }} accounts</span><div class="admin-pagination">{{ $players->links() }}</div></div>
</section>
@endsection
