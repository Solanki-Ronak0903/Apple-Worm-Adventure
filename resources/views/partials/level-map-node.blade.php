@php
    $record = $progress->get($level->id);
    $isUnlocked = $level->number === 1 || (bool) ($record?->unlocked);
    $isCompleted = (bool) ($record?->completed);
    $isWormStop = $level->id === $wormLevelId;
    $isFinishStop = $allLevelsComplete && $isWormStop;
    $earnedStars = max(0, min(3, (int) ($record?->stars ?? 0)));
    $stepClasses = trim(
        'worm-trail-step ' .
        ($isCompleted ? 'is-completed ' : '') .
        ($isWormStop && !$isFinishStop ? 'is-current ' : '') .
        ($isFinishStop ? 'is-finish worm-finish ' : '') .
        (!$isUnlocked ? 'is-locked ' : '')
    );
    $levelLabel = 'Level ' . str_pad($level->number, 2, '0', STR_PAD_LEFT) . ': ' . $level->title;
@endphp

@if($isUnlocked)
    <a class="{{ $stepClasses }}" style="grid-column: {{ $gridColumn }}" href="{{ route('game.play', $level) }}" aria-label="{{ $levelLabel }}. {{ $isCompleted ? 'Completed; replay level.' : 'Unlocked; play level.' }}">
@else
    <div class="{{ $stepClasses }}" style="grid-column: {{ $gridColumn }}" role="img" aria-label="{{ $levelLabel }}. Locked">
@endif
    <span class="trail-number">{{ str_pad($level->number, 2, '0', STR_PAD_LEFT) }}</span>
    <span class="trail-level-word" aria-hidden="true">LEVEL</span>
    <span class="trail-step-status" aria-hidden="true">{{ $isCompleted ? '🏅' : ($isWormStop ? '🐾' : ($isUnlocked ? '🍎' : '🔒')) }}</span>
    <span class="trail-mini-stars" aria-label="{{ $earnedStars }} of 3 stars">{{ str_repeat('★', $earnedStars) }}{{ str_repeat('☆', 3 - $earnedStars) }}</span>
    <span class="trail-step-accessible-title">{{ $level->title }}</span>

    @if($isWormStop)
        <span class="trail-worm-marker" aria-label="Your worm is here">
            <span class="trail-worm-character" aria-hidden="true">🐛</span>
            <span class="trail-worm-label">{{ $isFinishStop ? 'FINISH!' : 'YOU ARE HERE' }}</span>
        </span>
    @endif

    @if($connector === 'down')
        <span class="trail-connector--down" aria-hidden="true"></span>
    @endif
@if($isUnlocked)
    </a>
@else
    </div>
@endif
