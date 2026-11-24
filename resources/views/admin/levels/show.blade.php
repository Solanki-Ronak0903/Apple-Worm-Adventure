@extends('layouts.admin')
@section('title', 'Manage ' . $level->title)
@section('section', 'LEVEL ' . str_pad($level->number, 2, '0', STR_PAD_LEFT))
@section('content')
<div class="admin-page-head">
    <div><a class="admin-back-link admin-inline-back" href="{{ route('admin.levels') }}">← Back to level library</a><span class="admin-kicker">LEVEL {{ str_pad($level->number, 2, '0', STR_PAD_LEFT) }} · {{ strtoupper($worldName) }}</span><h1>{{ $level->title }}<span>.</span></h1><p>{{ $layout['lesson'] ?? 'This record does not contain side-view puzzle metadata.' }}</p></div>
    <div class="admin-level-status"><span class="admin-pill {{ $level->is_published ? 'good' : 'warn' }}">{{ $level->is_published ? 'Published' : 'Draft / hidden' }}</span><span class="admin-pill info">{{ ucfirst($level->difficulty) }}</span></div>
</div>

<div class="admin-detail-grid admin-level-detail-stats">
    <article class="admin-detail-stat"><small>Player completions</small><strong>{{ number_format($level->completed_players_count) }}</strong></article>
    <article class="admin-detail-stat"><small>Game sessions</small><strong>{{ number_format($level->game_sessions_count) }}</strong></article>
    <article class="admin-detail-stat"><small>Required apples</small><strong>{{ count($layout['apples'] ?? []) + count($layout['goldenApples'] ?? []) }}</strong></article>
    <article class="admin-detail-stat"><small>3-star target</small><strong>{{ (int) ($layout['parMoves'] ?? 0) }} <small>moves</small></strong></article>
    <article class="admin-detail-stat"><small>Move limit</small><strong>{{ (int) ($layout['moveLimit'] ?? 0) }} <small>moves</small></strong></article>
</div>

<div class="admin-level-manage-grid">
    <section class="admin-panel">
        <div class="admin-panel-head"><div><h2>Puzzle map preview</h2><p>Read-only view of the active server-side layout</p></div><span class="admin-pill info">{{ $layout ? ($layout['width'] ?? 14) . ' × ' . ($layout['height'] ?? 8) : 'No map' }}</span></div>
        <div class="admin-panel-body">
            @if($layout)
                <div class="admin-map-legend">
                    <span><i class="terrain"></i> Terrain</span><span><i class="worm"></i> Worm</span><span>🍎 Apple</span><span>✨ Golden</span><span>📦 Crate</span><span>✹ Spikes</span><span>🔘 Rune</span><span>🌀 Portal</span>
                </div>
                <div class="admin-map-scroll">
                    <div class="admin-map-preview" style="grid-template-columns:repeat({{ (int) ($layout['width'] ?? 14) }}, minmax(22px, 1fr))" role="img" aria-label="Level {{ $level->number }} puzzle map preview">
                        @foreach($previewRows as $row)
                            @foreach($row as $cell)
                                <span class="admin-map-cell {{ $cell }}" title="{{ str_replace('-', ' ', ucfirst($cell)) }}">
                                    @switch($cell)
                                        @case('apple') 🍎 @break
                                        @case('golden') ✨ @break
                                        @case('crate') 📦 @break
                                        @case('spike') ✹ @break
                                        @case('switch') ◉ @break
                                        @case('gate') ▥ @break
                                        @case('portal') 🌀 @break
                                        @case('worm-head') 🐛 @break
                                        @case('worm-body') ● @break
                                    @endswitch
                                </span>
                            @endforeach
                        @endforeach
                    </div>
                </div>
                <div class="admin-map-facts">
                    <span>Start: {{ ucfirst($layout['start']['facing'] ?? 'right') }} · {{ $layout['start']['length'] ?? 3 }} segments</span>
                    <span>{{ count($layout['blocks'] ?? []) }} crates</span>
                    <span>{{ count($layout['spikes'] ?? []) }} spike tiles</span>
                    <span>{{ count($layout['switches'] ?? []) }} runes</span>
                    <span>{{ count($layout['gates'] ?? []) }} gates</span>
                </div>
            @else
                <div class="admin-empty">This level is not configured for the current side-view puzzle engine.</div>
            @endif
        </div>
    </section>

    <section class="admin-panel admin-level-release-panel">
        <div class="admin-panel-head"><div><h2>Release controls</h2><p>Validate the server solution before publishing</p></div></div>
        <div class="admin-panel-body">
            <div class="admin-release-state"><span class="admin-release-dot {{ $level->is_published ? 'live' : '' }}"></span><div><strong>{{ $level->is_published ? 'Live in player campaign' : 'Hidden from players' }}</strong><small>{{ $level->is_published ? 'Players can discover this level when it is unlocked.' : 'This level is not listed in the player campaign.' }}</small></div></div>
            <form method="POST" action="{{ route('admin.levels.validate', $level) }}" class="admin-action-form">@csrf<button class="admin-button admin-button-wide" type="submit">◎ Validate puzzle solution</button></form>
            <form method="POST" action="{{ route('admin.levels.publication', $level) }}" class="admin-action-form" onsubmit="return confirm('{{ $level->is_published ? 'Unpublish this level? Existing progress will be preserved.' : 'Validate and publish this level to the player campaign?' }}')">@csrf @method('PATCH')<button class="admin-button admin-button-wide {{ $level->is_published ? 'admin-button-danger' : '' }}" type="submit">{{ $level->is_published ? 'Unpublish level' : 'Validate & publish' }}</button></form>
            <p class="admin-help-text">Publishing replays the approved solution through the production puzzle service. The validator creates and removes a temporary admin-owned session; it does not grant coins, achievements or player progress.</p>
        </div>
    </section>
</div>

@if($layout)
<section class="admin-panel admin-level-edit-panel">
    <div class="admin-panel-head"><div><h2>Level metadata</h2><p>Edit presentation text and difficulty without altering puzzle geometry or its verified route.</p></div><span class="admin-pill good">Safe fields only</span></div>
    <form method="POST" action="{{ route('admin.levels.update', $level) }}" class="admin-level-form">@csrf @method('PUT')
        <div class="admin-form-grid">
            <label class="admin-form-field"><span>Level title</span><input class="admin-field" name="title" maxlength="80" required value="{{ old('title', $level->title) }}"></label>
            <label class="admin-form-field"><span>Difficulty</span><select class="admin-field" name="difficulty" required>@foreach(['tutorial','easy','medium','hard','advanced'] as $item)<option value="{{ $item }}" @selected(old('difficulty', $level->difficulty) === $item)>{{ ucfirst($item) }}</option>@endforeach</select></label>
            <label class="admin-form-field admin-form-full"><span>Player lesson / objective</span><input class="admin-field" name="lesson" maxlength="180" required value="{{ old('lesson', $layout['lesson'] ?? '') }}"></label>
            @for($i = 0; $i < 3; $i++)
                <label class="admin-form-field admin-form-full"><span>Progressive hint {{ $i + 1 }}</span><textarea class="admin-field admin-textarea" name="hint_{{ $i + 1 }}" maxlength="240" required>{{ old('hint_' . ($i + 1), $hintStages[$i] ?? '') }}</textarea></label>
            @endfor
        </div>
        <div class="admin-form-footer"><span>World, start position, terrain, items, crates, hazards and portal are protected in this release.</span><button class="admin-button" type="submit">Save level details</button></div>
    </form>
</section>
@else
<div class="admin-level-note admin-level-edit-panel"><span>ⓘ</span><div><strong>Legacy level record</strong><p>This record does not use the active side-view puzzle format. It cannot be edited or published from this workspace until it has a reviewed side-view layout and an approved solution.</p></div></div>
@endif
@endsection
