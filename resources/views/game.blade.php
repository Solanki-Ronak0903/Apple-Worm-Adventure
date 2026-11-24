@extends('layouts.app')

@section('title', $level->title . ' · Apple Worm Adventure')

@section('content')
@php
    $nextLevel = \App\Models\Level::where('is_published', true)
        ->where('number', $level->number + 1)
        ->first();
    $nextLevelUrl = $nextLevel ? route('game.play', $nextLevel) : route('levels');
    $levelConfig = $level->config ?? [];
    $world = $levelConfig['world'] ?? ['name' => 'Puzzle World', 'theme' => 'meadow'];
    $mission = $levelConfig['mission'] ?? ['title' => 'One tasty adventure!', 'description' => 'Collect every apple and reach the portal.'];
    $hasKeyGate = !empty($levelConfig['keys']) || !empty($levelConfig['switches']) || !empty($levelConfig['gates']);
    $walletCoins = (int) (auth()->user()->wallet?->coins ?? 0);
    $fruitTotal = count($levelConfig['apples'] ?? []) + count($levelConfig['goldenApples'] ?? []) + count($levelConfig['megaApples'] ?? []) + count($levelConfig['mysteryApples'] ?? []);
    $requiredLength = (int) ($levelConfig['mission']['requiredLength'] ?? 0);
@endphp

<section
    class="game-page game-redesign game-topdown"
    data-world-theme="{{ $world['theme'] ?? 'meadow' }}"
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
    <div class="game-challenge-ribbon">
        <a href="{{ route('levels') }}" class="challenge-map-link"><span aria-hidden="true">🗺️</span> Forest map</a>
        <div class="challenge-ribbon-copy"><span aria-hidden="true">🍎</span> APPLE WORM ADVENTURE <i>·</i> LEVEL CHALLENGE</div>
        <div class="challenge-ribbon-actions">
            <a href="{{ route('wallet') }}" class="challenge-coin-pill" aria-label="Wallet balance {{ $walletCoins }} coins"><span aria-hidden="true">🪙</span> {{ number_format($walletCoins) }}</a>
            <a href="{{ route('leaderboard') }}" class="challenge-leaderboard-link">Leaderboard ↗</a>
        </div>
    </div>

    <header class="game-hero-bar">
        <div class="game-title-group">
            <a href="{{ route('levels') }}" class="game-back-link">← Adventure map</a>
            <div class="game-title-line">
                <span class="game-title-mascot" aria-hidden="true">🐛</span>
                <div>
                    <span class="game-eyebrow">WORLD {{ intdiv($level->number - 1, 5) + 1 }} · {{ strtoupper($world['name'] ?? 'APPLE WORM ADVENTURE') }}</span>
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
            <div class="game-hud-item hud-length">
                <span class="hud-icon" aria-hidden="true">🐛</span>
                <span><small>LENGTH</small><strong id="wormLength">1{{ !empty($levelConfig['mission']['requiredLength']) ? '/' . $levelConfig['mission']['requiredLength'] : '' }}</strong></span>
            </div>
            <div class="game-hud-item hud-golden-apples">
                <span class="hud-icon" aria-hidden="true">🌟</span>
                <span><small>GOLDEN</small><strong id="goldenAppleCount">0/{{ count($level->config['goldenApples'] ?? []) }}</strong></span>
            </div>
            <div class="game-hud-item hud-revive">
                <span class="hud-icon" aria-hidden="true">❤️</span>
                <span><small>REVIVE</small><strong id="reviveCount">{{ max(0, 1 - (int) $session->revives_used) }}</strong></span>
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
                    <span class="game-eyebrow">TOP-DOWN PUZZLE ADVENTURE · {{ $levelConfig['layout']['label'] ?? 'PUZZLE ROUTE' }}</span>
                    <h2>{{ $world['name'] ?? 'Puzzle World' }} <small class="platform-world-label">WORLD {{ intdiv($level->number - 1, 5) + 1 }}</small></h2>
                </div>
                <div class="board-legend" aria-label="Board legend">
                    <span><i class="legend-worm"></i> Worm</span>
                    <span><i class="legend-apple"></i> Apple</span>
                    <span><i class="legend-rock"></i> Rock</span>
                    <span><i class="legend-portal"></i> Portal</span>
                    @if(!empty($levelConfig['keys']))
                        <span><i class="legend-key"></i> Key</span>
                    @endif
                    @if(!empty($levelConfig['switches']))
                        <span><i class="legend-switch"></i> Switch</span>
                    @endif
                    @if($hasKeyGate)
                        <span><i class="legend-gate"></i> Gate</span>
                    @endif
                    @if(!empty($levelConfig['megaApples']))
                        <span><i class="legend-mega"></i> Mega</span>
                    @endif
                    @if(!empty($levelConfig['mysteryApples']))
                        <span><i class="legend-mystery"></i> Mystery</span>
                    @endif
                    @if(!empty($levelConfig['thorns']))
                        <span><i class="legend-thorn"></i> Thorns</span>
                    @endif
                    @if(!empty($levelConfig['mudPits']))
                        <span><i class="legend-mud"></i> Mud</span>
                    @endif
                </div>
            </div>

            <div class="game-board-stage topdown-board-stage" data-theme="{{ $world['theme'] ?? 'meadow' }}">
                <div class="topdown-scene-glow glow-one" aria-hidden="true"></div>
                <div class="topdown-scene-glow glow-two" aria-hidden="true"></div>
                <div
                    id="gameBoard"
                    class="game-board topdown-board"
                    role="img"
                    aria-label="Top-down 2D puzzle board. Use arrow keys, WASD, swipe or the direction pad to move the worm."
                ></div>
                <div id="pauseOverlay" class="pause-overlay" hidden>
                    <div class="pause-card">
                        <span class="pause-icon">Ⅱ</span>
                        <strong>Adventure paused</strong>
                        <span>Your worm is taking a little break.</span>
                        <button id="resumeGameBtn" type="button">Resume adventure</button>
                    </div>
                </div>
            </div>

            <div class="game-board-footer">
                <div class="mission-progress-copy">
                    <span>🍎 Fruit hunt</span>
                    <strong id="appleProgressText">0 of {{ count($level->config['apples'] ?? []) + count($level->config['goldenApples'] ?? []) + count($level->config['megaApples'] ?? []) + count($level->config['mysteryApples'] ?? []) }} collected</strong>
                </div>
                <div class="mission-progress-track" role="progressbar" aria-label="Fruit collection progress" aria-valuemin="0" aria-valuemax="{{ max(1, count($level->config['apples'] ?? []) + count($level->config['goldenApples'] ?? []) + count($level->config['megaApples'] ?? []) + count($level->config['mysteryApples'] ?? [])) }}" aria-valuenow="0" id="appleProgressTrack">
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
                <button id="pauseBtn" class="game-tool-btn" type="button" aria-pressed="false"><span>Ⅱ</span> Pause</button>
                <button id="restartBtn" class="game-tool-btn" type="button"><span>↻</span> Restart</button>
                <button id="hintBtn" class="game-tool-btn" type="button"><span>💡</span> Hint</button>
                <button id="soundBtn" class="game-tool-btn" type="button" aria-pressed="false"><span>♫</span> Sound off</button>
            </div>
            <p id="gameNotice" class="game-notice" role="status" aria-live="polite"></p>
            <p class="game-instructions">Plan your path, collect every fruit, avoid hazards and reach the portal. Every move counts!</p>
        </main>

        <aside class="game-sidebar game-sidebar-new">
            <section class="side-card game-mission-card">
                <div class="side-card-heading">
                    <span class="side-card-icon">🗺️</span>
                    <span class="game-eyebrow">THE MISSION · {{ strtoupper($level->difficulty) }}</span>
                </div>
                <h3>{{ $mission['title'] ?? 'One tasty adventure!' }}</h3>
                <p>{{ $mission['description'] ?? 'Guide your little worm, collect every apple and reach the portal.' }}</p>
                <div class="mission-objective-meter">
                    <div class="mission-objective-heading"><span>MISSION PROGRESS</span><strong id="missionProgressValue">{{ $requiredLength ? '1/' . $requiredLength : '0/' . max(1, $fruitTotal) }}</strong></div>
                    <div class="mission-objective-track" id="missionProgressMeter" role="progressbar" aria-label="Mission progress" aria-valuemin="0" aria-valuemax="{{ max(1, $requiredLength ?: $fruitTotal) }}" aria-valuenow="0"><span id="missionProgressFill"></span></div>
                    <div class="mission-objective-foot"><span id="missionObjectiveCopy">{{ $requiredLength ? 'Grow your worm' : 'Collect every fruit' }}</span><strong id="portalStateText">Portal sleeping</strong></div>
                </div>
                <div class="mission-check-row"><span>🍎 Collect all apples</span><b id="missionApples">0 left</b></div>
                <div class="mission-check-row"><span>🌟 Bonus golden apples</span><b id="missionGoldenApples">0 found</b></div>
                @if(!empty($levelConfig['mission']['requiredLength']))
                    <div class="mission-check-row"><span>🐛 Grow your worm</span><b id="missionLength">1/{{ (int) $levelConfig['mission']['requiredLength'] }}</b></div>
                @endif
                @if(!empty($levelConfig['megaApples']))
                    <div class="mission-check-row"><span>🍎 Mega apples</span><b id="missionMegaApples">{{ count($levelConfig['megaApples']) }} left</b></div>
                @endif
                @if(!empty($levelConfig['mysteryApples']))
                    <div class="mission-check-row"><span>❔ Mystery apples</span><b id="missionMysteryApples">{{ count($levelConfig['mysteryApples']) }} left</b></div>
                @endif
                @if(!empty($levelConfig['keys']))
                    <div class="mission-check-row" id="missionKeysRow"><span>🗝️ Find the key</span><b id="missionKeys">{{ count($levelConfig['keys'] ?? []) }} left</b></div>
                @endif
                @if(!empty($levelConfig['switches']))
                    <div class="mission-check-row" id="missionSwitchesRow"><span>🔘 Activate the switch</span><b id="missionSwitches">{{ count($levelConfig['switches'] ?? []) }} left</b></div>
                @endif
                <div class="mission-check-row"><span>🌀 Reach the portal</span><b>To finish</b></div>
                <div class="mission-check-row"><span>👣 Stay within moves</span><b id="missionMoves">{{ $level->config['moveLimit'] ?? 40 }} moves</b></div>
            </section>

            <section class="side-card game-path-card">
                <div class="side-card-heading">
                    <span class="side-card-icon">🧭</span>
                    <span class="game-eyebrow">ON THIS PATH</span>
                </div>
                <div class="path-guide-list">
                    <div><span class="path-guide-icon guide-golden">🌟</span><p><strong>Golden apple</strong><small>+50 points · +1 segment</small></p></div>
                    <div><span class="path-guide-icon guide-mega">🍎</span><p><strong>Mega apple</strong><small>+3 segments · +10 points</small></p></div>
                    <div><span class="path-guide-icon guide-mystery">❔</span><p><strong>Mystery apple</strong><small>Surprise effect · +10 points</small></p></div>
                    <div><span class="path-guide-icon guide-wall">🪨</span><p><strong>Stone wall</strong><small>Crash = fail</small></p></div>
                    <div><span class="path-guide-icon guide-thorn">🌵</span><p><strong>Thorns</strong><small>Touch = fail</small></p></div>
                    <div><span class="path-guide-icon guide-mud">🟤</span><p><strong>Mud pit</strong><small>Can't cross · go around</small></p></div>
                    <div><span class="path-guide-icon guide-portal">🌀</span><p><strong>Portal</strong><small>Exit once the goal is done</small></p></div>
                </div>
            </section>

            <section class="side-card game-scoring-card">
                <div class="side-card-heading">
                    <span class="side-card-icon">🏅</span>
                    <span class="game-eyebrow">SCORING</span>
                </div>
                <div class="scoring-row"><span>🍎 Regular apple</span><strong>+10 pts</strong></div>
                <div class="scoring-row"><span>🌟 Golden apple</span><strong>+50 pts</strong></div>
                <div class="scoring-row"><span>🌀 Reach the portal</span><strong>+100 pts</strong></div>
                <p class="scoring-note">First-time level rewards are credited to your coin wallet after a successful completion.</p>
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
                <div><small>FRUIT</small><strong id="resultApples">0/0</strong></div>
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
