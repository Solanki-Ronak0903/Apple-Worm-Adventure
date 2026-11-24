@extends('layouts.app')

@section('title', 'Side-view Gameplay Lab · Apple Worm Adventure')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/platformer.css') }}">
@endpush

@section('content')
<section class="awa-platformer-page" id="platformerPrototype">
    <script type="application/json" id="platformerLevelConfig">{!! json_encode($platformerLevel, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
    <div class="awa-platformer-heading">
        <div>
            <a class="awa-back-link" href="{{ route('levels') }}">← Back to adventure map</a>
            <div class="awa-lab-kicker"><span class="awa-live-indicator"></span> GAMEPLAY LAB <span class="awa-kicker-divider">/</span> NEW ENGINE PROTOTYPE</div>
            <h1>A whole new <span>adventure.</span></h1>
            <p>Meet the side-view Apple Worm prototype. Run, jump between platforms, collect apples and reach the portal.</p>
        </div>
        <div class="awa-prototype-stamp"><span>BUILD</span><strong>01</strong><small>PLAYABLE PREVIEW</small></div>
    </div>

    <div class="awa-platformer-hud" aria-label="Game statistics">
        <div class="awa-hud-brand"><span class="awa-hud-worm">🐛</span><span><small>ADVENTURE</small><strong>MEADOW RUN</strong></span></div>
        <div class="awa-hud-stat"><span class="awa-hud-symbol">🍎</span><span><small>APPLES</small><strong id="platformerAppleCount">0 / 4</strong></span></div>
        <div class="awa-hud-stat"><span class="awa-hud-symbol">⭐</span><span><small>SCORE</small><strong id="platformerScore">0000</strong></span></div>
        <div class="awa-hud-stat"><span class="awa-hud-symbol">🐛</span><span><small>WORM LENGTH</small><strong id="platformerLength">6</strong></span></div>
        <div class="awa-hud-stat awa-hud-lives"><span class="awa-hud-symbol">❤️</span><span><small>HEARTS</small><strong id="platformerLives">3</strong></span></div>
    </div>

    <div class="awa-platformer-game-card">
        <div class="awa-stage-topline">
            <div><span class="awa-stage-world">WORLD 01 <i>•</i> THE GREEN MEADOWS</span><strong id="platformerStatus">Find the apples and open the portal</strong></div>
            <div class="awa-stage-actions">
                <button type="button" class="awa-stage-button" id="platformerPause" aria-pressed="false"><span>Ⅱ</span> Pause</button>
                <button type="button" class="awa-stage-button awa-reset-button" id="platformerRestart"><span>↻</span> Restart</button>
            </div>
        </div>

        <div class="awa-canvas-frame" id="platformerCanvasFrame">
            <canvas id="platformerCanvas" width="1100" height="620" role="img" aria-label="Side-view platform puzzle. Move with the arrow keys or A and D. Jump with Space or Up. Collect all four apples, avoid the thorns and enter the portal."></canvas>
            <div class="awa-canvas-label"><span class="awa-canvas-label-dot"></span> SIDE-VIEW ADVENTURE PREVIEW</div>
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

    <div class="awa-platformer-footnote">
        <div class="awa-footnote-icon">🧪</div>
        <div><strong>Experimental gameplay preview</strong><p>This build tests the new movement, jumping, platforms and worm animation. It is separate from your saved levels and wallet; completing this preview does not award coins or change account progress.</p></div>
    </div>

    <div class="awa-platformer-help-grid">
        <article class="awa-help-card"><span class="awa-help-icon">🏃</span><div><strong>Move around</strong><p>Hold left or right to run. Build a little momentum before your jumps.</p></div></article>
        <article class="awa-help-card"><span class="awa-help-icon">🦘</span><div><strong>Time your jumps</strong><p>Press Space, W or ↑ to jump across gaps and reach higher platforms.</p></div></article>
        <article class="awa-help-card"><span class="awa-help-icon">🍎</span><div><strong>Collect every apple</strong><p>Each apple grows your worm and adds to your score. The portal opens when all four are collected.</p></div></article>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/platformer.js') }}" defer></script>
@endpush
