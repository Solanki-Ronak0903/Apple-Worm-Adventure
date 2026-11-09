
@extends('layouts.app')

@section('title', 'Player Dashboard')

@section('content')

<style>
.dash-reset-panel{display:flex;align-items:center;justify-content:space-between;gap:22px;margin:28px 0 8px;padding:20px 22px;border:1px solid #f0d8cb;border-radius:22px;background:linear-gradient(120deg,#fffaf5,#fff3ec)}
.dash-reset-copy{min-width:0}.dash-reset-copy h2{margin:5px 0 6px;color:#714b3d;font-size:clamp(17px,2vw,21px)}
.dash-reset-copy p{max-width:680px;margin:0;color:#8b7167;font-size:13px;line-height:1.6}
.dash-reset-details{flex:0 0 auto}.dash-reset-details summary{cursor:pointer;list-style:none;padding:11px 15px;border:1px solid #e5b9a9;border-radius:13px;background:#fff;color:#a45443;font-size:13px;font-weight:800;text-align:center}
.dash-reset-details summary::-webkit-details-marker{display:none}.dash-reset-details summary:before{content:'↻ '}
.dash-reset-details[open]{width:min(320px,100%)}.dash-reset-details form{display:grid;gap:9px;margin-top:12px;padding:14px;border:1px solid #f0d8cb;border-radius:15px;background:#fff;box-shadow:0 8px 22px rgba(112,72,55,.08)}
.dash-reset-details label{color:#79594d;font-size:12px;font-weight:700}.dash-reset-details input{width:100%;min-width:0;padding:11px 12px;border:1px solid #e6d4ca;border-radius:10px;color:#493a34;background:#fffdfb;box-sizing:border-box}
.dash-reset-details form button{padding:11px 13px;border:0;border-radius:10px;background:#dc755c;color:#fff;font-weight:800;cursor:pointer}.dash-reset-details form button:hover{background:#c95e48}.dash-reset-error{color:#b54837;font-size:12px}
@media(max-width:680px){.dash-reset-panel{align-items:stretch;flex-direction:column;padding:17px}.dash-reset-details,.dash-reset-details[open]{width:100%}.dash-reset-details summary{width:100%;box-sizing:border-box}}
</style>

@php
    $publishedLevels = \App\Models\Level::where('is_published', true)
        ->orderBy('number')
        ->get();

    $playerProgress = $user->progress;
    $progressByLevel = $playerProgress->keyBy('level_id');

    $starsEarned = (int) $playerProgress->sum('stars');

    $progressPercent = $total > 0
        ? min(100, (int) round(($completed / $total) * 100))
        : 0;

    $nextLevel = $publishedLevels->first(function ($level) use ($progressByLevel) {
        $progress = $progressByLevel->get($level->id);

        return $level->number === 1 ||
            ($progress && $progress->unlocked && !$progress->completed);
    });

    $previewLevels = $publishedLevels->take(4);
@endphp

<section class="dashboard-page">

    {{-- Welcome header --}}
    <header class="dashboard-hello">
        <div>
            <span class="dash-kicker">
                <i>✦</i> PLAYER DASHBOARD
            </span>

            <h1>
                Hey, {{ $user->name }} <span>🐛</span>
            </h1>

            <p>Your next little adventure is just one clever move away.</p>
        </div>

        <a class="dash-primary"
           href="{{ $nextLevel ? route('game.play', $nextLevel) : route('levels') }}">
            {{ $nextLevel ? 'Continue adventure' : 'Explore levels' }}
            <span>➜</span>
        </a>
    </header>


    {{-- Adventure welcome section --}}
    <section class="dash-welcome">

        <div class="dash-welcome-copy">
            <span class="dash-kicker">🍎 YOUR ADVENTURE SO FAR</span>

            <h2>
                Little moves.<br>
                <span style="color:#ff8c58">Big achievements.</span>
            </h2>

            <p>
                Collect apples, find the portal and earn stars as you
                make your way through the puzzle world. Every level
                is a new chance to shine!
            </p>

            <a class="dash-primary" href="{{ route('levels') }}">
                Explore all levels <span>→</span>
            </a>
        </div>

        <div class="dash-welcome-art"
             aria-label="A cheerful worm and an apple">

            <span class="dash-cloud">☁️</span>
            <span class="dash-star">⭐</span>
            <span class="dash-worm">🐛</span>
            <span class="dash-apple">🍎</span>

        </div>

    </section>


    {{-- Player statistics --}}
    <section class="dash-stat-grid" aria-label="Your game statistics">

        <article class="dash-stat" style="--bubble:#fff0bf">
            <span class="dash-stat-icon">🪙</span>
            <small>AVAILABLE COINS</small>

            <strong>{{ number_format($wallet->coins) }}</strong>

            <a href="{{ route('wallet') }}">Open wallet →</a>
        </article>


        <article class="dash-stat" style="--bubble:#e5f6d8">
            <span class="dash-stat-icon">🧩</span>
            <small>LEVELS COMPLETED</small>

            <strong>
                {{ $completed }}
                <em>/ {{ $total }}</em>
            </strong>

            <a href="{{ route('levels') }}">Choose a level →</a>
        </article>


        <article class="dash-stat" style="--bubble:#fff0d9">
            <span class="dash-stat-icon">⭐</span>
            <small>STARS EARNED</small>

            <strong>{{ number_format($starsEarned) }}</strong>

            <a href="{{ route('levels') }}">Earn more stars →</a>
        </article>


        <article class="dash-stat" style="--bubble:#e3f5fa">
            <span class="dash-stat-icon">🏆</span>
            <small>ADVENTURE PROGRESS</small>

            <strong>{{ $progressPercent }}%</strong>

            <a href="{{ route('leaderboard') }}">View leaderboard →</a>
        </article>

    </section>

    {{-- Achievement collection --}}
    <section class="dash-achievements-panel" aria-labelledby="dashAchievementsTitle">
        <div class="dash-achievements-heading">
            <div>
                <span class="dash-kicker">🏆 LITTLE MILESTONES</span>
                <h2 id="dashAchievementsTitle">Your achievements</h2>
                <p>Earn badges as you collect apples, clear levels and explore rewards.</p>
            </div>
            <span class="dash-achievement-count">{{ $achievementRecords->whereNotNull('unlocked_at')->count() }} / {{ $achievements->count() }} unlocked</span>
        </div>

        <div class="dash-achievement-grid">
            @foreach($achievements as $achievement)
                @php
                    $achievementRecord = $achievementRecords->get($achievement->id);
                    $criteria = (array) ($achievement->criteria ?? []);
                    $metric = collect($criteria)->keys()->first(fn ($key) => $key !== 'reward_coins');
                    $target = $metric ? (int) ($criteria[$metric] ?? 0) : 0;
                    $storedProgress = (array) ($achievementRecord?->progress ?? []);
                    $current = (int) ($storedProgress[$metric] ?? match ($metric) {
                        'levels' => $completed,
                        'stars' => $starsEarned,
                        'golden_apples' => $goldenApplesEarned,
                        default => 0,
                    });
                    $percent = $target > 0 ? min(100, (int) round(($current / $target) * 100)) : 0;
                    $isUnlocked = (bool) ($achievementRecord?->unlocked_at);
                @endphp
                <article class="dash-achievement-card {{ $isUnlocked ? 'is-unlocked' : 'is-locked' }}">
                    <span class="dash-achievement-icon" aria-hidden="true">{{ $achievement->icon }}</span>
                    <div class="dash-achievement-copy">
                        <strong>{{ $achievement->name }}</strong>
                        <small>{{ $achievement->description }}</small>
                        @if($isUnlocked)
                            <span class="dash-achievement-state">Unlocked · {{ $achievementRecord->unlocked_at->format('d M Y') }}</span>
                        @else
                            <div class="dash-achievement-progress" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                                <span style="width: {{ $percent }}%"></span>
                            </div>
                            <small class="dash-achievement-meta">{{ $current }} / {{ $target }} · Reward {{ (int) ($criteria['reward_coins'] ?? 0) }} coins</small>
                        @endif
                    </div>
                    @if($isUnlocked)<span class="dash-achievement-check">✓</span>@endif
                </article>
            @endforeach
        </div>
    </section>

    {{-- Main dashboard content --}}
    <div class="dash-main-grid">

        <div>

            {{-- Progress and level preview --}}
            <section class="dash-panel">

                <div class="dash-panel-head">

                    <div>
                        <span class="dash-kicker">YOUR JOURNEY</span>

                        <h2>Look how far you've wiggled!</h2>

                        <p>
                            Every completed puzzle brings you closer
                            to the portal.
                        </p>
                    </div>

                    <a class="dash-link" href="{{ route('levels') }}">
                        All levels →
                    </a>

                </div>


                <div class="dash-progress-meta">
                    <span>Adventure progress</span>

                    <strong>
                        {{ $completed }} of {{ $total }} levels
                    </strong>
                </div>


                <div class="dash-progress-track"
                     role="progressbar"
                     aria-valuenow="{{ $progressPercent }}"
                     aria-valuemin="0"
                     aria-valuemax="100"
                     aria-label="Adventure progress">

                    <div class="dash-progress-fill"
                         style="width:{{ $progressPercent }}%">
                    </div>

                </div>


                <div class="dash-progress-meta">
                    <span>{{ $progressPercent }}% complete</span>
                    <span>Keep going! 🌱</span>
                </div>


                {{-- Level preview --}}
                <div class="dash-level-list">

                    @forelse($previewLevels as $level)

                        @php
                            $levelProgress = $progressByLevel->get($level->id);

                            $isUnlocked = $level->number === 1 ||
                                (bool) ($levelProgress?->unlocked);
                        @endphp

                        <a
                            class="dash-level-tile {{ $isUnlocked ? '' : 'is-locked' }}"

                            href="{{ $isUnlocked ? route('game.play', $level) : 'javascript:void(0)' }}"

                            @if(!$isUnlocked)
                                aria-disabled="true"
                                tabindex="-1"
                            @endif
                        >

                            <span class="dash-level-top">

                                <strong class="dash-level-number">
                                    {{ str_pad($level->number, 2, '0', STR_PAD_LEFT) }}
                                </strong>

                                <span class="dash-level-star">
                                    {{ $levelProgress?->completed
                                        ? '★'
                                        : ($isUnlocked ? '☆' : '🔒') }}
                                </span>

                            </span>

                            <small>
                                {{ ucfirst($level->difficulty) }}
                            </small>

                            <b>
                                {{ $levelProgress?->completed
                                    ? 'Completed'
                                    : ($isUnlocked ? 'Ready to play' : 'Locked') }}
                            </b>

                        </a>

                    @empty

                        <div class="dash-empty">
                            No published levels are available yet.
                            Check back soon!
                        </div>

                    @endforelse

                </div>

            </section>


            {{-- Recent game sessions --}}
            <section class="dash-panel dash-recent">

                <div class="dash-panel-head">

                    <div>
                        <span class="dash-kicker">RECENT ADVENTURES</span>
                        <h2>Your latest game sessions</h2>
                    </div>

                </div>


                @if($recent->isEmpty())

                    <div class="dash-empty">
                        Your adventure is waiting! Pick a level to start
                        your first game and your recent sessions will
                        appear here. 🍎
                    </div>

                @else

                    <div style="overflow-x:auto">

                        <table>

                            <thead>
                                <tr>
                                    <th>Level</th>
                                    <th>Status</th>
                                    <th>Score</th>
                                    <th>Started</th>
                                </tr>
                            </thead>

                            <tbody>

                                @foreach($recent as $session)

                                    <tr>

                                        <td>
                                            {{ $session->level?->title ?? 'Puzzle level' }}
                                        </td>

                                        <td>
                                            <span class="dash-status">
                                                {{ ucfirst($session->status) }}
                                            </span>
                                        </td>

                                        <td>
                                            {{ number_format($session->score) }}
                                        </td>

                                        <td>
                                            {{ $session->created_at->diffForHumans() }}
                                        </td>

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                @endif

            </section>

        </div>


        {{-- Dashboard sidebar --}}
        <aside class="dash-side-stack">


            {{-- Continue adventure card --}}
            <section class="dash-continue-card">

                <span class="dash-kicker">🍏 UP NEXT</span>

                <h3>
                    {{ $nextLevel
                        ? 'Ready for another bite?'
                        : "You've explored every level!" }}
                </h3>

                <p>
                    {{ $nextLevel
                        ? 'Your next puzzle is waiting. Plan your path, collect every apple and find the portal.'
                        : 'Visit the level list to replay your favourite puzzles and improve your scores.' }}
                </p>

                <a href="{{ $nextLevel ? route('game.play', $nextLevel) : route('levels') }}">

                    {{ $nextLevel ? 'Play next level' : 'Browse levels' }}

                    <span>➜</span>

                </a>

            </section>


            {{-- Gameplay tip --}}
            <section class="dash-tip-card">

                <span class="tip-icon">💡</span>

                <h3>Little worm tip</h3>

                <p>
                    Take a moment to study the board before moving.
                    A clever plan can save moves and help you earn
                    more stars!
                </p>

            </section>


            {{-- Quick navigation --}}
            <section class="dash-panel" style="margin:0">

                <div class="dash-panel-head">

                    <div>
                        <span class="dash-kicker">QUICK JUMPS</span>
                        <h2>Where to next?</h2>
                    </div>

                </div>


                <div style="display:grid;gap:10px">

                    <a class="dash-level-tile"
                       href="{{ route('levels') }}">

                        <span class="dash-level-top">
                            <strong>🧩 Level map</strong>
                            <span>→</span>
                        </span>

                        <small>Find your next puzzle</small>

                    </a>


                    <a class="dash-level-tile"
                       href="{{ route('wallet') }}">

                        <span class="dash-level-top">
                            <strong>🪙 Coin wallet</strong>
                            <span>→</span>
                        </span>

                        <small>See your balance and rewards</small>

                    </a>


                    <a class="dash-level-tile"
                       href="{{ route('leaderboard') }}">

                        <span class="dash-level-top">
                            <strong>🏆 Leaderboard</strong>
                            <span>→</span>
                        </span>

                        <small>Check the player rankings</small>

                    </a>

                </div>

            </section>

        </aside>

    </div>

    {{-- Safe player-only progress reset --}}
    <section class="dash-reset-panel" aria-labelledby="dashResetTitle">
        <div class="dash-reset-copy">
            <span class="dash-kicker">ADVENTURE SETTINGS</span>
            <h2 id="dashResetTitle">🐛 Start your adventure again</h2>
            <p>Reset your completed levels, stars, achievements and game sessions. Your account, coins and wallet transaction history will stay safe.</p>
        </div>

        <details class="dash-reset-details">
            <summary>Reset my progress</summary>
            <form method="POST" action="{{ route('account.reset-progress') }}"
                  onsubmit="return confirm('Reset your adventure to Level 01? Your level progress, achievements and game sessions will be cleared. Your coins and wallet history will be kept.');">
                @csrf
                <label for="resetProgressPassword">Confirm with your current password</label>
                <input id="resetProgressPassword" type="password" name="password"
                       autocomplete="current-password" required
                       placeholder="Enter your current password">
                @error('password')
                    <small class="dash-reset-error">{{ $message }}</small>
                @enderror
                <button type="submit">Yes, reset my adventure</button>
            </form>
        </details>
    </section>

</section>

@endsection
