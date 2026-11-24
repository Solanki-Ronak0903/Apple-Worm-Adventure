@extends('layouts.admin')
@section('title', 'Level management')
@section('section', 'LEVEL MANAGEMENT')
@section('content')
<div class="admin-page-head">
    <div><span class="admin-kicker">CAMPAIGN OPERATIONS</span><h1>Level library<span>.</span></h1><p>Inspect the 25 hand-authored side-view puzzles, review their activity, and control their release status without changing the tested map geometry.</p></div>
    <div class="admin-date-chip">{{ number_format($publishedLevels) }} published · {{ number_format($draftLevels) }} drafts</div>
</div>

<div class="admin-stat-grid admin-level-stats">
    <article class="admin-stat-card" style="--accent:var(--ad-teal)"><div class="admin-stat-top"><span>Level records</span><span class="admin-stat-icon">▧</span></div><strong>{{ number_format($totalLevels) }}</strong><div class="admin-stat-foot">All level records in the game database</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-lime)"><div class="admin-stat-top"><span>Published</span><span class="admin-stat-icon">✓</span></div><strong>{{ number_format($publishedLevels) }}</strong><div class="admin-stat-foot">Visible in the player level map</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-amber)"><div class="admin-stat-top"><span>Draft / hidden</span><span class="admin-stat-icon">◷</span></div><strong>{{ number_format($draftLevels) }}</strong><div class="admin-stat-foot">Not currently available to players</div></article>
    <article class="admin-stat-card" style="--accent:var(--ad-blue)"><div class="admin-stat-top"><span>Game sessions</span><span class="admin-stat-icon">⌁</span></div><strong>{{ number_format($totalSessions) }}</strong><div class="admin-stat-foot">Recorded attempts across all levels</div></article>
</div>

<section class="admin-panel">
    <form class="admin-toolbar admin-level-toolbar" method="GET" action="{{ route('admin.levels') }}">
        <div class="admin-search-form">
            <input class="admin-field admin-search" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Search level title or number…" aria-label="Search levels">
            <select class="admin-field admin-select" name="world" aria-label="Filter world"><option value="all">All worlds</option>@foreach($worlds as $code => $name)<option value="{{ $code }}" @selected($world === $code)>{{ $name }}</option>@endforeach</select>
            <select class="admin-field admin-select" name="difficulty" aria-label="Filter difficulty">
                <option value="all">All difficulty</option>
                @foreach(['tutorial','easy','medium','hard','advanced'] as $item)<option value="{{ $item }}" @selected($difficulty === $item)>{{ ucfirst($item) }}</option>@endforeach
            </select>
            <select class="admin-field admin-select" name="status" aria-label="Filter publication"><option value="all" @selected($status === 'all')>All status</option><option value="published" @selected($status === 'published')>Published</option><option value="draft" @selected($status === 'draft')>Draft / hidden</option></select>
            <select class="admin-field admin-select" name="sort" aria-label="Sort levels"><option value="number_asc" @selected($sort === 'number_asc')>Level order</option><option value="number_desc" @selected($sort === 'number_desc')>Reverse order</option><option value="most_played" @selected($sort === 'most_played')>Most played</option><option value="most_completed" @selected($sort === 'most_completed')>Most completed</option></select>
            <button class="admin-button" type="submit">Apply filters</button>
            <a class="admin-reset-link" href="{{ route('admin.levels') }}">Reset</a>
        </div>
    </form>
    <div class="admin-table-wrap">
        <table class="admin-table admin-level-table">
            <thead><tr><th>Level</th><th>World</th><th>Difficulty</th><th>Plays</th><th>Players cleared</th><th>Release</th><th></th></tr></thead>
            <tbody>
            @forelse($levels as $level)
                @php($sideView = $level->config['sideView'] ?? [])
                <tr>
                    <td><div class="admin-level-cell"><span class="admin-level-number">{{ str_pad($level->number, 2, '0', STR_PAD_LEFT) }}</span><div><a class="admin-player-name" href="{{ route('admin.levels.show', $level) }}">{{ $level->title }}</a><span class="sub">{{ $sideView['lesson'] ?? 'No puzzle lesson metadata' }}</span></div></div></td>
                    <td><span class="admin-world-name">{{ $sideView['world']['name'] ?? 'Legacy / unassigned' }}</span></td>
                    <td><span class="admin-pill info">{{ ucfirst($level->difficulty) }}</span></td>
                    <td>{{ number_format($level->game_sessions_count) }}</td>
                    <td>{{ number_format($level->completed_players_count) }}</td>
                    <td><span class="admin-pill {{ $level->is_published ? 'good' : 'warn' }}">{{ $level->is_published ? 'Published' : 'Draft / hidden' }}</span></td>
                    <td><a class="admin-link" href="{{ route('admin.levels.show', $level) }}">Manage →</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="admin-empty">No levels match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-table-footer"><span>Showing {{ $levels->firstItem() ?? 0 }}–{{ $levels->lastItem() ?? 0 }} of {{ number_format($levels->total()) }} levels</span><div class="admin-pagination">{{ $levels->links() }}</div></div>
</section>

<div class="admin-level-note"><span>ⓘ</span><div><strong>Protected puzzle maps</strong><p>This release supports metadata editing and server-validated publication. Tile-by-tile map editing and new level creation are intentionally reserved for the next editor milestone so an untested layout cannot damage the live campaign.</p></div></div>
@endsection
