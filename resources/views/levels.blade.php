@extends('layouts.app')

@section('title', 'Worm Adventure Trail')

@section('content')
@php
    $levelCount = $levels->count();
    $completedCount = $progress->where('completed', true)->count();
    $starsEarned = (int) $progress->sum('stars');
    $completionPercent = $levelCount > 0
        ? min(100, (int) round(($completedCount / $levelCount) * 100))
        : 0;

    // The worm stands at the first unlocked level that is not yet completed.
    $currentLevel = $levels->first(function ($level) use ($progress) {
        $record = $progress->get($level->id);
        $unlocked = $level->number === 1 || (bool) ($record?->unlocked);
        return $unlocked && !(bool) ($record?->completed);
    });

    $allLevelsComplete = $levelCount > 0 && $currentLevel === null;
    $lastLevelId = $levels->last()?->id;
    $wormLevelId = $currentLevel?->id ?? ($allLevelsComplete ? $lastLevelId : null);

    $unlockedCount = $levels->filter(function ($level) use ($progress) {
        $record = $progress->get($level->id);
        return $level->number === 1 || (bool) ($record?->unlocked);
    })->count();

    // Each breakpoint gets its own serpentine row grouping.
    $desktopRows = $levels->values()->chunk(5)->map(fn ($row) => $row->values())->values();
    $tabletRows = $levels->values()->chunk(3)->map(fn ($row) => $row->values())->values();
    $mobileRows = $levels->values()->chunk(2)->map(fn ($row) => $row->values())->values();
@endphp

<style>
    .worm-adventure-map {
        --worm-forest: #28583f;
        --worm-leaf: #76c982;
        --worm-leaf-dark: #399660;
        --worm-mint: #eaf9df;
        --worm-cream: #fffef7;
        --worm-sun: #ffd45d;
        --worm-coral: #ff9061;
        color: var(--worm-forest);
    }

    .worm-adventure-map,
    .worm-adventure-map * { box-sizing: border-box; }

    .worm-map-hero {
        position: relative;
        overflow: hidden;
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) minmax(230px, .8fr);
        gap: 28px;
        align-items: center;
        padding: clamp(24px, 4vw, 46px);
        border: 1px solid #c8e9b7;
        border-radius: 30px;
        background: linear-gradient(115deg, #fbffed 0%, #e5f9e9 58%, #d9f5f4 100%);
        box-shadow: 0 10px 0 rgba(111, 183, 105, .16);
    }

    .worm-map-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #26815b;
        font-size: 11px;
        font-weight: 950;
        letter-spacing: .15em;
        text-transform: uppercase;
    }

    .worm-map-hero h1 {
        margin: 12px 0 10px;
        color: var(--worm-forest);
        font-size: clamp(34px, 5vw, 56px);
        line-height: 1.02;
        letter-spacing: -.045em;
    }

    .worm-map-hero h1 span { color: var(--worm-coral); }
    .worm-map-hero p { max-width: 590px; margin: 0; color: #648b78; font-size: 15px; line-height: 1.75; }

    .worm-map-scene {
        min-height: 178px;
        display: grid;
        place-items: center;
        position: relative;
        overflow: hidden;
        border: 2px solid #b9e6a9;
        border-radius: 25px;
        background: linear-gradient(180deg, #b9eeee 0%, #d9f8dc 72%, #80c981 72%);
    }
    .worm-map-scene .scene-worm {
        font-size: clamp(68px, 9vw, 100px);
        filter: drop-shadow(0 8px 5px #478b6b55);
        animation: sceneWormWiggle 2.8s ease-in-out infinite;
    }
    .worm-map-scene .scene-apple { position: absolute; right: 17%; top: 25%; font-size: 39px; animation: sceneBob 2.7s ease-in-out infinite; }
    .worm-map-scene .scene-star { position: absolute; left: 17%; top: 18%; font-size: 28px; }
    .worm-map-scene .scene-cloud { position: absolute; right: 13%; top: 10%; font-size: 25px; }

    .worm-map-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin: 24px 0 16px; }
    .worm-map-stat { min-width: 0; padding: 17px 18px; border: 1px solid #d8edcc; border-radius: 20px; background: var(--worm-cream); box-shadow: 0 7px 18px #397c4c0d; }
    .worm-map-stat .stat-icon { display: grid; place-items: center; width: 38px; height: 38px; margin-bottom: 10px; border-radius: 13px; background: #fff0bd; font-size: 20px; }
    .worm-map-stat:nth-child(2) .stat-icon { background: #e5f6d8; }
    .worm-map-stat:nth-child(3) .stat-icon { background: #ffe7d7; }
    .worm-map-stat small { display: block; color: #71907e; font-size: 10px; font-weight: 950; letter-spacing: .1em; }
    .worm-map-stat strong { display: block; margin-top: 3px; color: var(--worm-forest); font-size: 27px; line-height: 1.2; }
    .worm-map-stat strong span { color: #8ba18e; font-size: 14px; font-weight: 700; }

    .worm-map-progress { margin: 0 0 27px; padding: 17px 20px; border: 1px solid #d8edcc; border-radius: 18px; background: var(--worm-cream); }
    .worm-map-progress-head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 10px; color: #527b65; font-size: 13px; font-weight: 850; }
    .worm-map-progress-track { height: 12px; overflow: hidden; border-radius: 20px; background: #e8f0df; }
    .worm-map-progress-fill { height: 100%; border-radius: inherit; background: linear-gradient(90deg, #75ce80, #b7df58); transition: width .6s ease; }

    .worm-map-current-note { display: flex; align-items: center; gap: 10px; margin: 0 0 24px; padding: 12px 16px; border: 1px solid #f2df9f; border-radius: 16px; background: #fff7d9; color: #80652b; font-size: 13px; line-height: 1.5; }
    .worm-map-current-note .note-worm { font-size: 25px; flex: 0 0 auto; }

    .worm-map-section-head { display: flex; justify-content: space-between; align-items: end; gap: 16px; margin: 30px 2px 16px; }
    .worm-map-section-head h2 { margin: 5px 0 0; color: var(--worm-forest); font-size: clamp(23px, 3vw, 31px); }
    .worm-map-section-head p { margin: 4px 0 0; color: #77917f; font-size: 13px; }
    .worm-map-legend { display: flex; flex-wrap: wrap; gap: 12px; color: #668675; font-size: 11px; font-weight: 850; }
    .worm-map-legend span { display: inline-flex; align-items: center; gap: 5px; }
    .worm-map-legend i { width: 10px; height: 10px; border-radius: 50%; background: #76c982; }
    .worm-map-legend .legend-current { background: #ffc83d; }
    .worm-map-legend .legend-locked { background: #cbd5c9; }

    /* The map is now a trail, not a grid of large cards. */
    .worm-trail-board {
        --trail-cols: 5;
        --trail-row-gap: 43px;
        display: grid;
        gap: var(--trail-row-gap);
        padding: 27px 22px;
        border: 1px solid #d7edcc;
        border-radius: 27px;
        background:
            radial-gradient(circle at 12% 15%, #ffffff9c 0 2px, transparent 3px),
            radial-gradient(circle at 84% 78%, #ffffff9c 0 2px, transparent 3px),
            linear-gradient(180deg, #f4ffe9, #eaf9e7);
        overflow: visible;
    }

    .worm-trail-row {
        position: relative;
        display: grid;
        grid-template-columns: repeat(var(--trail-cols), minmax(0, 1fr));
        align-items: center;
        min-height: 94px;
        isolation: isolate;
    }

    /* Dashed green path between each set of small level stops. */
    .worm-trail-row::before {
        content: "";
        position: absolute;
        z-index: 0;
        left: calc(50% / var(--trail-cols));
        right: calc(50% / var(--trail-cols));
        top: 50%;
        height: 9px;
        transform: translateY(-50%);
        border: 2px solid #fff9dc;
        border-radius: 999px;
        background: repeating-linear-gradient(90deg, #80ca83 0 17px, #a6d76e 17px 27px, #d6e9a8 27px 34px);
        background-size: 68px 100%;
        box-shadow: 0 3px 6px #548b4c1c;
        animation: trailFlow 3.6s linear infinite;
    }

    .worm-trail-step {
        position: relative;
        z-index: 2;
        justify-self: center;
        display: grid;
        place-items: center;
        width: 76px;
        height: 76px;
        border: 4px solid #fffef4;
        border-radius: 50%;
        background: linear-gradient(145deg, #efffe7, #c8f0c5);
        color: #28583f;
        text-decoration: none;
        box-shadow: 0 5px 0 #a8d8a2, 0 7px 16px #397c4c20;
        transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
        isolation: isolate;
    }

    a.worm-trail-step:hover { transform: translateY(-4px) scale(1.05); box-shadow: 0 8px 0 #a8d8a2, 0 12px 20px #397c4c25; }
    a.worm-trail-step:focus-visible { outline: 4px solid #f2bd35; outline-offset: 5px; }
    .worm-trail-step.is-completed { background: linear-gradient(145deg, #a9e6a5, #6fc982); box-shadow: 0 5px 0 #4cae6c, 0 7px 16px #397c4c20; }
    .worm-trail-step.is-current { width: 84px; height: 84px; border-color: #fff3c1; background: linear-gradient(145deg, #ffe993, #ffc83d); color: #765018; box-shadow: 0 6px 0 #e5ad2e, 0 0 0 7px #ffd85b45, 0 9px 20px #b9872a25; animation: currentStopPulse 2.3s ease-in-out infinite; }
    .worm-trail-step.is-finish { background: linear-gradient(145deg, #d9f1ff, #a6d9f4); box-shadow: 0 5px 0 #8ec3e4, 0 7px 16px #397c4c20; }
    .worm-trail-step.is-locked { border-color: #f8f9f1; background: #e7ecdf; color: #96aa98; box-shadow: 0 4px 0 #d3ddce; cursor: not-allowed; }

    .trail-number { display: block; color: inherit; font-size: 23px; font-weight: 1000; letter-spacing: -.04em; line-height: 1; }
    .trail-level-word { margin-top: -12px; color: currentColor; font-size: 8px; font-weight: 950; letter-spacing: .14em; opacity: .75; }
    .trail-step-status { position: absolute; top: -3px; right: -5px; display: grid; place-items: center; width: 25px; height: 25px; border: 2px solid #fffef4; border-radius: 50%; background: #fffef4; font-size: 13px; line-height: 1; box-shadow: 0 2px 6px #28583f20; }
    .is-completed .trail-step-status { background: #fff6cb; }
    .is-current .trail-step-status { background: #fffef4; }
    .is-locked .trail-step-status { background: #f2f3eb; }

    .trail-mini-stars { position: absolute; bottom: -22px; left: 50%; display: flex; gap: 2px; transform: translateX(-50%); color: #e9ad25; font-size: 10px; letter-spacing: 0; white-space: nowrap; text-shadow: 0 1px #fff; }
    .is-locked .trail-mini-stars { color: #b8c3b0; }
    .trail-step-accessible-title { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

    /* The turn at the end of a row joins the next row at the same edge. */
    .trail-connector--down {
        position: absolute;
        z-index: -1;
        top: 50%;
        left: 50%;
        width: 9px;
        height: calc(100% + var(--trail-row-gap, 43px));
        transform: translateX(-50%);
        border: 2px solid #fff9dc;
        border-top: 0;
        border-radius: 0 0 999px 999px;
        background: repeating-linear-gradient(180deg, #80ca83 0 17px, #a6d76e 17px 27px, #d6e9a8 27px 34px);
        background-size: 100% 68px;
        box-shadow: 2px 3px 6px #548b4c1c;
        animation: trailFlowVertical 3.6s linear infinite;
        pointer-events: none;
    }

    .trail-worm-marker {
        position: absolute;
        z-index: 5;
        top: -39px;
        left: 50%;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px 3px 4px;
        border: 1px solid #f2d26b;
        border-radius: 999px;
        background: #fff8d6;
        box-shadow: 0 4px 10px #80652b20;
        white-space: nowrap;
        transform: translateX(-50%);
    }
    .trail-worm-character { display: inline-block; font-size: 25px; line-height: 1; filter: drop-shadow(0 3px 2px #397c4c30); transform-origin: 55% 80%; animation: wormArrive .85s cubic-bezier(.2,.85,.25,1.25) both, wormIdle 2.3s ease-in-out .85s infinite; }
    .trail-worm-label { color: #80611b; font-size: 8px; font-weight: 950; letter-spacing: .07em; }
    .worm-finish .trail-worm-marker { border-color: #9acbe7; background: #e8f7ff; }
    .worm-finish .trail-worm-label { color: #356a8c; }

    .worm-map-empty { padding: 28px; border: 1px dashed #b9d9ae; border-radius: 18px; color: #668675; text-align: center; }
    .worm-map-note { margin-top: 16px; padding: 14px 17px; border-radius: 16px; background: #fff4cf; color: #80652b; font-size: 12px; line-height: 1.6; }

    .worm-trail-board--tablet, .worm-trail-board--mobile { display: none; }

    @keyframes sceneWormWiggle { 0%,100% { transform: translateY(0) rotate(-3deg); } 50% { transform: translateY(-7px) rotate(3deg); } }
    @keyframes sceneBob { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-7px); } }
    @keyframes wormArrive { 0% { opacity: 0; transform: translate(-28px, 7px) rotate(-18deg) scale(.78); } 70% { opacity: 1; transform: translate(4px, -5px) rotate(8deg) scale(1.08); } 100% { opacity: 1; transform: translate(0, 0) rotate(0) scale(1); } }
    @keyframes wormIdle { 0%,100% { transform: translateY(0) rotate(-4deg); } 50% { transform: translateY(-4px) rotate(5deg); } }
    @keyframes currentStopPulse { 0%,100% { box-shadow: 0 6px 0 #e5ad2e, 0 0 0 5px #ffd85b32, 0 9px 20px #b9872a25; } 50% { box-shadow: 0 6px 0 #e5ad2e, 0 0 0 9px #ffd85b20, 0 9px 20px #b9872a25; } }
    @keyframes trailFlow { to { background-position: 68px 0; } }
    @keyframes trailFlowVertical { to { background-position: 0 68px; } }

    @media (max-width: 1100px) {
        .worm-trail-board--desktop { display: none; }
        .worm-trail-board--tablet { display: grid; --trail-cols: 3; }
    }
    @media (max-width: 700px) {
        .worm-map-hero { grid-template-columns: 1fr; gap: 18px; border-radius: 23px; }
        .worm-map-scene { min-height: 145px; }
        .worm-map-stats { gap: 9px; }
        .worm-map-stat { padding: 13px; }
        .worm-map-stat strong { font-size: 23px; }
        .worm-map-stat small { font-size: 9px; }
        .worm-map-section-head { align-items: flex-start; flex-direction: column; }
        .worm-map-legend { gap: 10px; }
        .worm-trail-board--tablet { display: none; }
        .worm-trail-board--mobile { display: grid; --trail-cols: 2; --trail-row-gap: 42px; padding: 23px 12px; gap: var(--trail-row-gap); }
        .worm-trail-board--mobile .worm-trail-row { min-height: 90px; }
        .worm-trail-board--mobile .worm-trail-step { width: 68px; height: 68px; }
        .worm-trail-board--mobile .worm-trail-step.is-current { width: 76px; height: 76px; }
        .worm-trail-board--mobile .trail-number { font-size: 21px; }
        .worm-trail-board--mobile .trail-worm-marker { top: -35px; }
        .worm-trail-board--mobile .trail-worm-character { font-size: 22px; }
    }
    @media (max-width: 390px) {
        .worm-map-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .worm-map-stat strong { font-size: 20px; }
        .worm-trail-board--mobile { padding-inline: 8px; }
        .worm-trail-board--mobile .worm-trail-step { width: 63px; height: 63px; }
        .worm-trail-board--mobile .worm-trail-step.is-current { width: 71px; height: 71px; }
        .worm-trail-board--mobile .trail-number { font-size: 19px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .worm-map-scene .scene-worm, .worm-map-scene .scene-apple,
        .trail-worm-character, .worm-map-progress-fill,
        .worm-trail-step, .worm-trail-row::before, .trail-connector--down {
            animation: none !important;
            transition: none !important;
        }
    }
</style>

<section class="worm-adventure-map">
    <header class="worm-map-hero">
        <div>
            <span class="worm-map-kicker">🧭 YOUR PUZZLE WORLD</span>
            <h1>Follow the trail.<br><span>Find your next apple!</span></h1>
            <p>Help your wiggly friend travel from level to level. Finish a puzzle, earn your stars and watch the worm move to its next stop on the adventure trail.</p>
        </div>
        <div class="worm-map-scene" aria-label="A cartoon worm, apple and star">
            <span class="scene-star" aria-hidden="true">⭐</span>
            <span class="scene-worm" aria-hidden="true">🐛</span>
            <span class="scene-apple" aria-hidden="true">🍎</span>
            <span class="scene-cloud" aria-hidden="true">☁️</span>
        </div>
    </header>

    <div class="worm-map-stats">
        <article class="worm-map-stat">
            <span class="stat-icon">🧩</span>
            <small>LEVELS UNLOCKED</small>
            <strong>{{ $unlockedCount }}<span> / {{ $levelCount }}</span></strong>
        </article>
        <article class="worm-map-stat">
            <span class="stat-icon">⭐</span>
            <small>STARS COLLECTED</small>
            <strong>{{ number_format($starsEarned) }}</strong>
        </article>
        <article class="worm-map-stat">
            <span class="stat-icon">🏁</span>
            <small>LEVELS CLEARED</small>
            <strong>{{ $completedCount }}<span> / {{ $levelCount }}</span></strong>
        </article>
    </div>

    <div class="worm-map-progress">
        <div class="worm-map-progress-head"><span>Your adventure progress</span><strong>{{ $completionPercent }}%</strong></div>
        <div class="worm-map-progress-track" role="progressbar" aria-valuenow="{{ $completionPercent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Completed levels">
            <div class="worm-map-progress-fill" style="width: {{ $completionPercent }}%"></div>
        </div>
    </div>

    @if($currentLevel)
        <div class="worm-map-current-note">
            <span class="note-worm" aria-hidden="true">🐛</span>
            <span>Your worm is waiting at <strong>Level {{ str_pad($currentLevel->number, 2, '0', STR_PAD_LEFT) }}</strong>. Complete it and your little friend will move to the next stop!</span>
        </div>
    @elseif($allLevelsComplete)
        <div class="worm-map-current-note">
            <span class="note-worm" aria-hidden="true">🐛</span>
            <span><strong>Trail complete!</strong> Your worm reached the final stop. Replay levels to improve your score and stars.</span>
        </div>
    @endif

    <div class="worm-map-section-head">
        <div>
            <span class="worm-map-kicker">🍏 THE WIGGLY WAY</span>
            <h2>Follow your worm's path</h2>
            <p>Small level stops connected by one winding trail. Follow the worm to find where your adventure continues.</p>
        </div>
        <div class="worm-map-legend" aria-label="Level status legend">
            <span><i></i> Completed</span>
            <span><i class="legend-current"></i> Current</span>
            <span><i class="legend-locked"></i> Locked</span>
        </div>
    </div>

    @if($levels->isEmpty())
        <div class="worm-map-empty">No published levels are available yet. Your adventure will appear here soon! 🍎</div>
    @else
        {{-- Desktop: five small stops per row, alternating direction. --}}
        <div class="worm-trail-board worm-trail-board--desktop" aria-label="Winding level trail">
            @foreach($desktopRows as $rowIndex => $row)
                @php $isReverse = ($rowIndex % 2) === 1; @endphp
                <div class="worm-trail-row">
                    @foreach($row as $position => $level)
                        @php
                            $lastInRow = $position === $row->count() - 1;
                            $hasNextRow = $lastInRow && $rowIndex < $desktopRows->count() - 1;
                            $connector = $hasNextRow ? 'down' : '';
                            $gridColumn = $isReverse ? 5 - $position : $position + 1;
                        @endphp
                        @include('partials.level-map-node', [
                            'level' => $level, 'progress' => $progress,
                            'wormLevelId' => $wormLevelId, 'allLevelsComplete' => $allLevelsComplete,
                            'connector' => $connector, 'gridColumn' => $gridColumn,
                        ])
                    @endforeach
                </div>
            @endforeach
        </div>

        {{-- Tablet: three small stops per row. --}}
        <div class="worm-trail-board worm-trail-board--tablet" aria-label="Winding level trail">
            @foreach($tabletRows as $rowIndex => $row)
                @php $isReverse = ($rowIndex % 2) === 1; @endphp
                <div class="worm-trail-row">
                    @foreach($row as $position => $level)
                        @php
                            $lastInRow = $position === $row->count() - 1;
                            $hasNextRow = $lastInRow && $rowIndex < $tabletRows->count() - 1;
                            $connector = $hasNextRow ? 'down' : '';
                            $gridColumn = $isReverse ? 3 - $position : $position + 1;
                        @endphp
                        @include('partials.level-map-node', [
                            'level' => $level, 'progress' => $progress,
                            'wormLevelId' => $wormLevelId, 'allLevelsComplete' => $allLevelsComplete,
                            'connector' => $connector, 'gridColumn' => $gridColumn,
                        ])
                    @endforeach
                </div>
            @endforeach
        </div>

        {{-- Mobile: two small stops per row. --}}
        <div class="worm-trail-board worm-trail-board--mobile" aria-label="Winding level trail">
            @foreach($mobileRows as $rowIndex => $row)
                @php $isReverse = ($rowIndex % 2) === 1; @endphp
                <div class="worm-trail-row">
                    @foreach($row as $position => $level)
                        @php
                            $lastInRow = $position === $row->count() - 1;
                            $hasNextRow = $lastInRow && $rowIndex < $mobileRows->count() - 1;
                            $connector = $hasNextRow ? 'down' : '';
                            $gridColumn = $isReverse ? 2 - $position : $position + 1;
                        @endphp
                        @include('partials.level-map-node', [
                            'level' => $level, 'progress' => $progress,
                            'wormLevelId' => $wormLevelId, 'allLevelsComplete' => $allLevelsComplete,
                            'connector' => $connector, 'gridColumn' => $gridColumn,
                        ])
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    <div class="worm-map-note">🐾 <strong>Worm wisdom:</strong> Complete the current puzzle to move your little friend forward. Each finished stop turns green, and the next level unlocks through your existing game progress.</div>
</section>
@endsection
