@extends('layouts.app')

@section('title', $level->title . ' · Apple Worm Adventure')

@section('content')
@php
    $nextLevel = \App\Models\Level::where('is_published', true)
        ->where('number', $level->number + 1)
        ->first();
    $nextLevelUrl = $nextLevel ? route('game.play', $nextLevel) : route('levels');
@endphp

<section
    class="game-page game-redesign"
    id="gameApp"
    data-level='@json($level->config)'
    data-level-id="{{ $level->id }}"
    data-session-id="{{ $session->id }}"
    data-level-number="{{ $level->number }}"
    data-next-level-url="{{ $nextLevelUrl }}"
    data-replay-level-url="{{ route('game.play', $level) }}"
    data-has-next-level="{{ $nextLevel ? '1' : '0' }}"
    data-revives-used="{{ $session->revives_used }}"
>
    <header class="game-hero-bar">
        <div class="game-title-group">
            <a href="{{ route('levels') }}" class="game-back-link">← Adventure map</a>
            <div class="game-title-line">
                <span class="game-title-mascot" aria-hidden="true">🐛</span>
                <div>
                    <span class="game-eyebrow">APPLE WORM ADVENTURE</span>
                    <h1>{{ $level->title }}</h1>
                </div>
                <span class="game-difficulty diff-{{ $level->difficulty }}">{{ ucfirst($level->difficulty) }}</span>
            </div>
        </div>

        <div class="game-hud" aria-label="Game statistics">
            <div class="game-hud-item hud-score">
                <span class="hud-icon" aria-hidden="true">🏆</span>
                <span><small>SCORE</small><strong id="score">0</strong></span>
            </div>
            <div class="game-hud-item hud-moves">
                <span class="hud-icon" aria-hidden="true">👣</span>
                <span><small>MOVES</small><strong><span id="moves">0</span><i>/</i><span id="moveLimit">{{ $level->config['moveLimit'] ?? 40 }}</span></strong></span>
            </div>
            <div class="game-hud-item hud-apples">
                <span class="hud-icon" aria-hidden="true">🍎</span>
                <span><small>APPLES</small><strong id="appleCount">0/{{ count($level->config['apples'] ?? []) }}</strong></span>
            </div>
            <div class="game-hud-item hud-golden-apples">
                <span class="hud-icon" aria-hidden="true">🌟</span>
                <span><small>GOLDEN</small><strong id="goldenAppleCount">0/{{ count($level->config['goldenApples'] ?? []) }}</strong></span>
            </div>
        </div>
    </header>

    <div class="game-status-strip">
        <span class="game-live-dot" aria-hidden="true"></span>
        <span id="gameStatus">Get ready, little worm!</span>
        <span class="game-level-pill">LEVEL {{ str_pad($level->number, 2, '0', STR_PAD_LEFT) }}</span>
    </div>

    <div class="game-layout game-layout-new">
        <main class="board-panel game-board-panel">
            <div class="game-board-heading">
                <div>
                    <span class="game-eyebrow">YOUR PUZZLE WORLD</span>
                    <h2>Collect apples. Find the portal!</h2>
                </div>
                <div class="board-legend" aria-label="Board legend">
                    <span><i class="legend-worm"></i> Worm</span>
                    <span><i class="legend-apple"></i> Apple</span>
                    <span><i class="legend-rock"></i> Rock</span>
                    <span><i class="legend-portal"></i> Portal</span>
                </div>
            </div>

            <div class="game-board-stage">
                <div class="stage-cloud cloud-one" aria-hidden="true">☁️</div>
                <div class="stage-cloud cloud-two" aria-hidden="true">☁️</div>
                <div class="stage-sparkle sparkle-one" aria-hidden="true">✦</div>
                <div class="stage-sparkle sparkle-two" aria-hidden="true">✧</div>
                <div
                    id="gameBoard"
                    class="game-board"
                    role="img"
                    aria-label="Puzzle board. Use arrow keys, WASD, swipe or the direction pad to move the worm."
                ></div>
                <div class="stage-ground" aria-hidden="true"></div>
            </div>

            <div class="game-board-footer">
                <div class="mission-progress-copy">
                    <span>🍎 Apple hunt</span>
                    <strong id="appleProgressText">0 of {{ count($level->config['apples'] ?? []) }} collected</strong>
                </div>
                <div class="mission-progress-track" role="progressbar" aria-label="Apple collection progress" aria-valuemin="0" aria-valuemax="{{ max(1, count($level->config['apples'] ?? [])) }}" aria-valuenow="0" id="appleProgressTrack">
                    <span id="appleProgressFill"></span>
                </div>
            </div>

            <div class="game-play-controls">
                <div class="game-control-intro">
                    <span class="game-eyebrow">STEER YOUR WORM</span>
                    <small>Arrow keys · WASD · Swipe</small>
                </div>
                <div class="game-controls game-dpad" aria-label="Touch movement controls">
                    <button class="control-btn dpad-up" type="button" data-dir="up" aria-label="Move up">↑</button>
                    <div class="dpad-middle">
                        <button class="control-btn" type="button" data-dir="left" aria-label="Move left">←</button>
                        <button class="control-btn dpad-down" type="button" data-dir="down" aria-label="Move down">↓</button>
                        <button class="control-btn" type="button" data-dir="right" aria-label="Move right">→</button>
                    </div>
                </div>
            </div>

            <div class="game-action-row">
                <button id="restartBtn" class="game-tool-btn" type="button"><span>↻</span> Restart</button>
                <button id="hintBtn" class="game-tool-btn" type="button"><span>💡</span> Hint</button>
                <button id="soundBtn" class="game-tool-btn" type="button" aria-pressed="false"><span>♫</span> Sound off</button>
            </div>
            <p id="gameNotice" class="game-notice" role="status" aria-live="polite"></p>
            <p class="game-instructions">Collect every apple before entering the glowing portal. Plan your path carefully — every move counts!</p>
        </main>

        <aside class="game-sidebar game-sidebar-new">
            <section class="side-card game-mission-card">
                <div class="side-card-heading">
                    <span class="side-card-icon">🗺️</span>
                    <span class="game-eyebrow">TODAY'S MISSION</span>
                </div>
                <h3>One tasty adventure!</h3>
                <p>Guide your little worm around the rocks, munch every apple and reach the portal to finish the level.</p>
                <div class="mission-check-row"><span>🍎 Collect all apples</span><b id="missionApples">0 left</b></div>
                <div class="mission-check-row"><span>🌟 Bonus golden apples</span><b id="missionGoldenApples">0 found</b></div>
                <div class="mission-check-row"><span>🌀 Reach the portal</span><b>To finish</b></div>
                <div class="mission-check-row"><span>👣 Stay within moves</span><b id="missionMoves">{{ $level->config['moveLimit'] ?? 40 }} moves</b></div>
            </section>

            <section class="side-card game-power-card">
                <div class="side-card-heading">
                    <span class="side-card-icon">⚡</span>
                    <span class="game-eyebrow">POWER-UP STATION</span>
                </div>
                <h3>A little extra help?</h3>
                <p class="power-disclaimer">Development demo only — these actions simulate a rewarded ad. No real ad is shown.</p>
                <div class="reward-list">
                    <button class="reward-btn" type="button" data-reward="hint"><span class="reward-name">💡 Unlock a hint</span><span class="reward-tag">Ad demo</span></button>
                    <button class="reward-btn" type="button" data-reward="double_score"><span class="reward-name">⭐ Double score</span><span class="reward-tag">Ad demo</span></button>
                    <button class="reward-btn" type="button" data-reward="double_coins"><span class="reward-name">🪙 Double coins</span><span class="reward-tag">Ad demo</span></button>
                    <button class="reward-btn" type="button" data-reward="extra_moves"><span class="reward-name">👣 +5 extra moves</span><span class="reward-tag">Ad demo</span></button>
                    <button class="reward-btn" type="button" data-reward="booster"><span class="reward-name">⚡ Apple booster</span><span class="reward-tag">Ad demo</span></button>
                    <button class="reward-btn" type="button" data-reward="revive"><span class="reward-name">🐛 Revive worm</span><span class="reward-tag">Ad demo</span></button>
                </div>
            </section>

            <a href="{{ route('wallet') }}" class="game-wallet-link">
                <span class="wallet-link-icon">🪙</span>
                <span><strong>My coin wallet</strong><small>View rewards and history</small></span>
                <b>→</b>
            </a>
        </aside>
    </div>

    <div id="gameModal" class="modal hidden game-modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="modalMessage">
        <div class="modal-card game-modal-card">
            <button id="modalClose" class="game-modal-close" type="button" aria-label="Close message">×</button>
            <div id="modalConfetti" class="game-modal-confetti" aria-hidden="true">⭐ 🍎 ✨ ⭐</div>
            <div id="modalEmoji" class="modal-emoji game-modal-emoji">🎉</div>
            <span id="modalEyebrow" class="game-eyebrow">APPLE WORM ADVENTURE</span>
            <h2 id="modalTitle">Level complete!</h2>
            <p id="modalMessage">Great job, little worm!</p>
            <div id="modalStars" class="stars big">★★★</div>

            <div id="gameResultStats" class="game-result-stats" hidden>
                <div><small>SCORE</small><strong id="resultScore">0</strong></div>
                <div><small>MOVES</small><strong id="resultMoves">0</strong></div>
                <div><small>APPLES</small><strong id="resultApples">0/0</strong></div>
                <div><small>GOLDEN</small><strong id="resultGoldenApples">0/0</strong></div>
                <div id="resultCoinsCard"><small>COINS</small><strong id="resultCoins">+0 🪙</strong></div>
            </div>

            <div class="modal-actions game-result-actions">
                <button id="modalPrimary" class="btn primary" type="button">Continue</button>
                <button id="modalSecondary" class="btn ghost" type="button" hidden>Extra action</button>
                <button id="modalRestart" class="btn ghost" type="button">Play again</button>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/game.js') }}" defer></script>
@endpush
