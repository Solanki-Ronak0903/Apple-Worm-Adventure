@extends('layouts.app')

@section('title', $level->title . ' · Apple Worm Adventure')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/puzzle-game.css') }}">
@endpush

@section('content')
@php
    $sideView = $level->config['sideView'];
    $clientSideView = $sideView;
    unset($clientSideView['hintStages']);
    $nextLevel = \App\Models\Level::where('is_published', true)->where('number', $level->number + 1)->first();
    $nextLevelUrl = $nextLevel ? route('game.play', $nextLevel) : route('levels');
    $walletCoins = (int) (auth()->user()->wallet?->coins ?? 0);
    $hintTotal = count($sideView['hintStages'] ?? []) + (\App\Game\SideViewLevelSolutions::for((int) $level->number) !== null ? 1 : 0);
    $rewardedPreviewEnabled = (bool) config('game.rewarded_ads.allow_development_mock', false)
        && (bool) \App\Models\AdminSetting::getValue('rewarded_actions_enabled', true);
    $hasLevelCompletionReward = \App\Models\UserLevelReward::query()
        ->where('user_id', auth()->id())
        ->where('level_id', $level->id)
        ->where('reward_code', 'level_completion')
        ->exists();
@endphp

<section class="puzzle-page" id="puzzleGame"
    data-layout="{{ base64_encode(json_encode($clientSideView)) }}"
    data-session-id="{{ $session->id }}"
    data-state-url="{{ route('game.state', $session) }}"
    data-move-url="{{ route('game.puzzle-move', $session) }}"
    data-restart-url="{{ route('game.restart', $session) }}"
    data-complete-url="{{ route('game.complete', $session) }}"
    data-hint-url="{{ route('game.hint', $session) }}"
    data-next-url="{{ $nextLevelUrl }}"
    data-has-next="{{ $nextLevel ? '1' : '0' }}"
    data-hint="{{ $sideView['hint'] }}"
    data-hint-total="{{ $hintTotal }}"
    data-par-moves="{{ $sideView['parMoves'] }}"
    data-move-limit="{{ $sideView['moveLimit'] ?? ($sideView['parMoves'] + 5) }}"
    data-reward-url="{{ route('game.reward', $session) }}"
    data-reward-preview-enabled="{{ $rewardedPreviewEnabled ? '1' : '0' }}"
    data-level-number="{{ $level->number }}"
    data-double-coins-eligible="{{ $level->number >= 6 && ! $hasLevelCompletionReward ? '1' : '0' }}"
    data-revive-reward-eligible="{{ $level->number >= 11 ? '1' : '0' }}">

    <div class="puzzle-topline">
        <a class="puzzle-back" href="{{ route('levels') }}">← Level map</a>
        <div class="puzzle-brandmark"><span>🐛</span><b>APPLE WORM <i>ADVENTURE</i></b></div>
        <a class="puzzle-wallet" href="{{ route('wallet') }}">🪙 {{ number_format($walletCoins) }}</a>
    </div>

    <header class="puzzle-heading">
        <div>
            <span class="puzzle-kicker">WORLD {{ intdiv($level->number - 1, 5) + 1 }} · {{ strtoupper($sideView['world']['name']) }}</span>
            <h1>{{ $level->title }}</h1>
            <p>{{ $sideView['lesson'] }}</p>
        </div>
        <div class="puzzle-level-badge">LEVEL <strong>{{ str_pad($level->number, 2, '0', STR_PAD_LEFT) }}</strong></div>
    </header>

    <section class="puzzle-hud" aria-label="Level statistics">
        <div><small>MOVES USED</small><strong id="puzzleMoves">0</strong></div>
        <div id="puzzleMovesLeftCard"><small>MOVES LEFT</small><strong id="puzzleMovesLeft">{{ $sideView['moveLimit'] ?? ($sideView['parMoves'] + 5) }}</strong></div>
        <div><small>APPLES</small><strong id="puzzleApples">0/{{ count($sideView['apples']) + count($sideView['goldenApples'] ?? []) }}</strong></div>
        <div><small>WORM LENGTH</small><strong id="puzzleLength">{{ $sideView['start']['length'] }}</strong></div>
        <div><small>CRATES MOVED</small><strong id="puzzlePushes">0</strong></div>
        <div><small>3-STAR TARGET</small><strong>{{ $sideView['parMoves'] }} moves</strong></div>
    </section>

    <main class="puzzle-game-card">
        <div class="puzzle-scene-head">
            <div class="puzzle-world-label"><span class="puzzle-live-dot"></span><span id="puzzleStatus">Find the apples, then reach the portal.</span></div>
            <div class="puzzle-tools">
                <button id="puzzleHint" type="button" title="Reveal a progressive hint for this puzzle" aria-controls="puzzleHintPanel">💡 Hint <span id="puzzleHintCount">0/{{ $hintTotal }}</span></button>
                <button id="puzzlePause" type="button" title="Pause puzzle">Ⅱ Pause</button>
                <button id="puzzleRestart" type="button" title="Restart level">↻ Restart</button>
            </div>
        </div>

        <div class="puzzle-hint-panel hidden" id="puzzleHintPanel" role="status" aria-live="polite">
            <div><span class="puzzle-hint-eyebrow">PUZZLE GUIDE</span><strong id="puzzleHintStage">Hint 1 of {{ $hintTotal }}</strong></div>
            <p id="puzzleHintText"></p>
        </div>

        @if($rewardedPreviewEnabled && $level->number >= 6)
            <div class="puzzle-reward-strip hidden" id="puzzleRewardStrip" aria-live="polite">
                <div class="puzzle-reward-copy">
                    <strong>Optional reward previews</strong>
                    <small>Development preview only · no real video ad is played.</small>
                </div>
                <button type="button" id="puzzleExtraMovesReward" class="hidden">🎬 Preview +5 moves</button>
                <button type="button" id="puzzleDoubleCoinsReward" class="hidden">🪙 Preview double coins</button>
            </div>
        @endif

        <div class="puzzle-canvas-wrap" data-theme="{{ $sideView['world']['theme'] }}">
            <canvas id="puzzleCanvas" width="1120" height="640" role="img" aria-label="Side-view worm puzzle. Use arrow keys or the movement controls to move one tile at a time. The worm grows when it collects apples, and its body can bridge a gap while at least one segment is supported."></canvas>
            <div class="puzzle-overlay hidden" id="puzzlePauseOverlay">
                <div class="puzzle-overlay-card"><strong>Paused</strong><span>Your worm is catching its breath.</span><button type="button" id="puzzleResume">Resume</button></div>
            </div>
            <div class="puzzle-toast" id="puzzleToast" role="status" aria-live="polite"></div>
        </div>

        <div class="puzzle-controls-row">
            <div class="puzzle-instructions">
                <strong>HOW TO PLAY</strong>
                <span>Arrow keys / WASD move one tile · Stay within the move limit · R restarts · Push crates · Activate runes · Collect every apple · Reach the portal</span>
            </div>
            <div class="puzzle-dpad" aria-label="Touch movement controls">
                <button type="button" data-puzzle-dir="up" aria-label="Move up">↑</button>
                <button type="button" data-puzzle-dir="left" aria-label="Move left">←</button>
                <button type="button" data-puzzle-dir="down" aria-label="Move down">↓</button>
                <button type="button" data-puzzle-dir="right" aria-label="Move right">→</button>
            </div>
        </div>
        <div class="puzzle-progress"><span id="puzzleProgressFill"></span></div>
    </main>

    <div class="puzzle-footnote"><span>🍎</span> Think about the whole body: an apple adds length, a supported segment can bridge a gap, and a rune switch opens its gate.</div>

    <div class="puzzle-result hidden" id="puzzleResult" role="dialog" aria-modal="true" aria-labelledby="puzzleResultTitle">
        <div class="puzzle-result-card">
            <div class="puzzle-result-icon" id="puzzleResultIcon">🎉</div>
            <span class="puzzle-kicker">APPLE WORM ADVENTURE</span>
            <h2 id="puzzleResultTitle">Level complete!</h2>
            <p id="puzzleResultMessage">Every apple found. Great puzzle solving!</p>
            <div class="puzzle-result-stats"><div><small>SCORE</small><strong id="puzzleResultScore">0</strong></div><div><small>MOVES</small><strong id="puzzleResultMoves">0</strong></div><div><small>REWARD</small><strong id="puzzleResultCoins">+0 🪙</strong></div></div>
            <div class="puzzle-result-actions">
                <button type="button" class="puzzle-primary-action" id="puzzleContinue">Continue</button>
                <button type="button" class="puzzle-secondary-action" id="puzzleRetry">Play again</button>
            </div>
        </div>
    </div>

    <div class="puzzle-result hidden" id="puzzleError" role="alertdialog" aria-modal="true" aria-labelledby="puzzleErrorTitle">
        <div class="puzzle-result-card">
            <div class="puzzle-result-icon">🐛</div><span class="puzzle-kicker">TRY ANOTHER ROUTE</span>
            <h2 id="puzzleErrorTitle">The worm fell!</h2><p id="puzzleErrorMessage">Take another look at the ledges.</p>
            <div class="puzzle-result-actions">
                <form method="POST" action="{{ route('game.restart', $session) }}">@csrf<button type="submit" class="puzzle-primary-action" id="puzzleErrorRetry">Try again</button></form>
                @if($rewardedPreviewEnabled && $level->number >= 11)
                    <button type="button" id="puzzleReviveReward" class="puzzle-reward-action hidden">🎬 Preview rewarded revive</button>
                @endif
                <a class="puzzle-secondary-action" href="{{ route('levels') }}">Level map</a>
            </div>
        </div>
    </div>
</section>

@push('scripts')
<script src="{{ asset('js/puzzle-game.js') }}?v={{ filemtime(public_path('js/puzzle-game.js')) }}" defer></script>
@endpush
@endsection
