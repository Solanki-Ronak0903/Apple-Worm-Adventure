@extends('layouts.app')

@section('title', 'Level ' . str_pad((string) $level->number, 2, '0', STR_PAD_LEFT) . ' · Apple Worm Adventure')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/platformer.css') }}">
@endpush

@section('content')
<section
    class="awa-platformer-page awa-live-level-page"
    id="platformerPrototype"
    data-complete-url="{{ route('game.platformer.complete', $session) }}"
    data-csrf="{{ csrf_token() }}"
    data-next-url="{{ $level->number < 25 ? route('game.play', $level->number + 1) : route('levels') }}"
    data-level-number="{{ $level->number }}"
>
    <script type="application/json" id="platformerLevelConfig">{!! json_encode($platformerLevel, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>

    <div class="awa-platformer-heading">
        <div>
            <a class="awa-back-link" href="{{ route('levels') }}">← Back to adventure map</a>
            <div class="awa-lab-kicker"><span class="awa-live-indicator"></span> WORLD {{ str_pad((string) $platformerLevel['worldIndex'], 2, '0', STR_PAD_LEFT) }} <span class="awa-kicker-divider">/</span> {{ strtoupper($platformerLevel['world']['title']) }}</div>
            <h1>{{ $platformerLevel['title'] }} <span>adventure.</span></h1>
            <p>Run, jump between grassy platforms, collect the apples and guide your worm to the portal.</p>
        </div>
        <div class="awa-prototype-stamp"><span>LEVEL</span><strong>{{ str_pad((string) $level->number, 2, '0', STR_PAD_LEFT) }}</strong><small>{{ $platformerLevel['difficulty'] }}</small></div>
    </div>

    <div class="awa-platformer-hud" aria-label="Game statistics">
        <div class="awa-hud-brand"><span class="awa-hud-worm">🐛</span><span><small>{{ strtoupper($platformerLevel['world']['name']) }}</small><strong>{{ strtoupper($platformerLevel['title']) }}</strong></span></div>
        <div class="awa-hud-stat"><span class="awa-hud-symbol">🍎</span><span><small>APPLES</small><strong id="platformerAppleCount">0 / {{ count($platformerLevel['apples']) }}</strong></span></div>
        <div class="awa-hud-stat"><span class="awa-hud-symbol">⭐</span><span><small>SCORE</small><strong id="platformerScore">0000</strong></span></div>
        <div class="awa-hud-stat"><span class="awa-hud-symbol">🐛</span><span><small>WORM LENGTH</small><strong id="platformerLength">{{ $platformerLevel['player']['initialSegments'] + 1 }}</strong></span></div>
        <div class="awa-hud-stat awa-hud-lives"><span class="awa-hud-symbol">❤️</span><span><small>HEARTS</small><strong id="platformerLives">{{ $platformerLevel['player']['lives'] }}</strong></span></div>
    </div>

    <div class="awa-platformer-game-card">
        <div class="awa-stage-topline">
            <div><span class="awa-stage-world">LEVEL {{ str_pad((string) $level->number, 2, '0', STR_PAD_LEFT) }} <i>•</i> {{ strtoupper($platformerLevel['world']['title']) }}</span><strong id="platformerStatus">Find the apples and open the portal</strong></div>
            <div class="awa-stage-actions">
                <button type="button" class="awa-stage-button" id="platformerPause" aria-pressed="false"><span>Ⅱ</span> Pause</button>
                <button type="button" class="awa-stage-button awa-reset-button" id="platformerRestart"><span>↻</span> Restart</button>
            </div>
        </div>

        <div class="awa-canvas-frame" id="platformerCanvasFrame">
            <canvas id="platformerCanvas" width="1100" height="620" role="img" aria-label="Side-view Apple Worm platform adventure. Move with the arrow keys or A and D. Jump with Space or Up. Collect all apples, avoid spikes and enter the portal."></canvas>
            <div class="awa-canvas-label"><span class="awa-canvas-label-dot"></span> SIDE-VIEW ADVENTURE <span>·</span> LEVEL {{ str_pad((string) $level->number, 2, '0', STR_PAD_LEFT) }}</div>
            <div class="awa-canvas-toast" id="platformerToast" role="status" aria-live="polite">A little jump goes a long way!</div>
            <div class="awa-canvas-overlay" id="platformerOverlay" hidden>
                <div class="awa-overlay-card">
                    <div class="awa-overlay-emoji" id="platformerOverlayEmoji">⏸️</div>
                    <span class="awa-stage-world" id="platformerOverlayEyebrow">TAKE A BREATHER</span>
                    <h2 id="platformerOverlayTitle">Adventure paused</h2>
                    <p id="platformerOverlayMessage">Your little worm is catching its breath.</p>
                    <button type="button" class="awa-primary-button" id="platformerOverlayAction">Resume adventure</button>
                </div>
            </div>
        </div>

        <div class="awa-control-bar">
            <div class="awa-key-hints"><span><kbd>←</kbd><kbd>→</kbd> or <kbd>A</kbd><kbd>D</kbd> <small>MOVE</small></span><span><kbd>SPACE</kbd> <small>JUMP</small></span></div>
            <div class="awa-touch-controls" aria-label="Touch game controls">
                <button type="button" class="awa-touch-button" data-platformer-control="left" aria-label="Move left">←</button>
                <button type="button" class="awa-touch-button" data-platformer-control="right" aria-label="Move right">→</button>
                <button type="button" class="awa-touch-button awa-jump-button" data-platformer-control="jump" aria-label="Jump">↑ <span>JUMP</span></button>
            </div>
        </div>
    </div>

    <div class="awa-platformer-footnote awa-live-level-note">
        <div class="awa-footnote-icon">🛡️</div>
        <div><strong>Adventure progress is protected</strong><p>Your movement recording is checked by the server before stars, level unlocks, achievements or wallet rewards are granted.</p></div>
    </div>

    <div class="awa-platformer-help-grid">
        <article class="awa-help-card"><span class="awa-help-icon">🏃</span><div><strong>Run across platforms</strong><p>Hold left or right to build momentum. Land carefully on the grassy platform tops.</p></div></article>
        <article class="awa-help-card"><span class="awa-help-icon">🦘</span><div><strong>Time your jumps</strong><p>Press Space, W or ↑ to leap over gaps and spikes. You can also use the touch controls.</p></div></article>
        <article class="awa-help-card"><span class="awa-help-icon">🍎</span><div><strong>Grow your worm</strong><p>Collect every red apple to open the portal. Golden apples are optional bonus treasure.</p></div></article>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/platformer.js') }}" defer></script>
@endpush
