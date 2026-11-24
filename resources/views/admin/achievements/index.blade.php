@extends('layouts.admin')
@section('title', 'Achievement management')
@section('section', 'ACHIEVEMENTS')
@section('content')
<div class="admin-page-head">
    <div><span class="admin-kicker">PLAYER PROGRESSION</span><h1>Achievement library<span>.</span></h1><p>Manage the goals players can unlock. Deactivation keeps historical badges and reward records intact.</p></div>
    <a class="admin-button admin-button-link" href="{{ route('admin.achievements.create') }}">＋ Create achievement</a>
</div>

<div class="admin-stat-grid admin-achievement-stats">
    <article class="admin-stat-card" style="--accent:var(--ad-teal)"><div class="admin-stat-top"><span>Achievement definitions</span><span class="admin-stat-icon">✦</span></div><strong>{{ number_format($totalAchievements) }}</strong><div class="admin-stat-foot">All configured achievements</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-lime)"><div class="admin-stat-top"><span>Active</span><span class="admin-stat-icon">✓</span></div><strong>{{ number_format($activeAchievements) }}</strong><div class="admin-stat-foot">Available for future progress</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-amber)"><div class="admin-stat-top"><span>Inactive</span><span class="admin-stat-icon">◷</span></div><strong>{{ number_format($inactiveAchievements) }}</strong><div class="admin-stat-foot">Hidden from new unlock progress</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-blue)"><div class="admin-stat-top"><span>Total unlocks</span><span class="admin-stat-icon">🏆</span></div><strong>{{ number_format($totalUnlocks) }}</strong><div class="admin-stat-foot">Historical player unlocks</div></article>
</div>

<section class="admin-panel">
    <form class="admin-toolbar" method="GET" action="{{ route('admin.achievements') }}">
        <div class="admin-search-form admin-achievement-filters">
            <input class="admin-field admin-search" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search name, code or description…" aria-label="Search achievements">
            <select class="admin-field admin-select" name="status" aria-label="Filter status"><option value="all" @selected($status === 'all')>All statuses</option><option value="active" @selected($status === 'active')>Active</option><option value="inactive" @selected($status === 'inactive')>Inactive</option></select>
            <select class="admin-field admin-select" name="metric" aria-label="Filter progress metric"><option value="all">All metrics</option>@foreach($metrics as $code => $label)<option value="{{ $code }}" @selected($metric === $code)>{{ $label }}</option>@endforeach</select>
            <select class="admin-field admin-select" name="sort" aria-label="Sort achievements"><option value="name" @selected($sort === 'name')>Name</option><option value="most_unlocked" @selected($sort === 'most_unlocked')>Most unlocked</option><option value="least_unlocked" @selected($sort === 'least_unlocked')>Least unlocked</option><option value="newest" @selected($sort === 'newest')>Newest</option></select>
            <button class="admin-button" type="submit">Apply filters</button><a class="admin-reset-link" href="{{ route('admin.achievements') }}">Reset</a>
        </div>
    </form>
    <div class="admin-table-wrap"><table class="admin-table admin-achievement-table">
        <thead><tr><th>Achievement</th><th>Unlock requirement</th><th>Coin reward</th><th>Players unlocked</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($achievements as $achievement)
            @php($criteria = (array) ($achievement->criteria ?? []))
            @php($criterion = collect($metrics)->keys()->first(fn ($key) => array_key_exists($key, $criteria)))
            <tr>
                <td><div class="admin-achievement-cell"><span class="admin-achievement-icon">{{ $achievement->icon }}</span><div><a class="admin-player-name" href="{{ route('admin.achievements.show', $achievement) }}">{{ $achievement->name }}</a><span class="sub">{{ $achievement->code }} · {{ $achievement->description }}</span></div></div></td>
                <td>{{ $criterion ? number_format((int) $criteria[$criterion]) . ' ' . strtolower($metrics[$criterion]) : 'Legacy criteria' }}</td>
                <td><span class="admin-coin-reward">{{ number_format((int) ($criteria['reward_coins'] ?? 0)) }} 🪙</span></td>
                <td>{{ number_format($achievement->unlocks_count) }} <span class="sub">/ {{ number_format($achievement->tracked_players_count) }} tracked</span></td>
                <td><span class="admin-pill {{ $achievement->is_active ? 'good' : 'warn' }}">{{ $achievement->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td><a class="admin-link" href="{{ route('admin.achievements.show', $achievement) }}">Manage →</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="admin-empty">No achievements match these filters.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="admin-table-footer"><span>Showing {{ $achievements->firstItem() ?? 0 }}–{{ $achievements->lastItem() ?? 0 }} of {{ number_format($achievements->total()) }} achievements</span><div class="admin-pagination">{{ $achievements->links() }}</div></div>
</section>
<div class="admin-level-note"><span>ⓘ</span><div><strong>Player history is protected</strong><p>Achievements cannot be deleted. Deactivating a definition stops future progress and unlocks, while earned badges and reward ledger entries remain unchanged. Progress metrics become locked after a player starts tracking a definition.</p></div></div>
@endsection
