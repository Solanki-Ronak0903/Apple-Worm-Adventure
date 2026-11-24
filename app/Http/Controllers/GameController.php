<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\Level;
use App\Models\UserLevelProgress;
use App\Models\WalletTransaction;
use App\Models\UserLevelReward;
use App\Models\Achievement;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use App\Services\GameHintService;
use App\Services\SideViewHintService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $wallet = app(WalletService::class)->getOrCreate($user);
        $total = Level::where('is_published', true)->count();

        // Dashboard statistics must reflect only levels available in this release.
        $publishedProgress = UserLevelProgress::with('level')
            ->where('user_id', $user->id)
            ->whereHas('level', fn ($query) => $query->where('is_published', true))
            ->get();
        $user->setRelation('progress', $publishedProgress);
        $completed = $publishedProgress->where('completed', true)->count();
        $goldenApplesEarned = (int) UserLevelReward::query()
            ->where('user_id', $user->id)
            ->where('reward_code', 'golden_apple_completion')
            ->whereHas('level', fn ($query) => $query->where('is_published', true))
            ->sum('quantity');

        $recent = $user->gameSessions()
            ->whereHas('level', fn ($query) => $query->where('is_published', true))
            ->latest()
            ->take(5)
            ->with('level')
            ->get();

        // Show active achievements, plus any inactive achievement this player
        // has already unlocked so historical badges remain visible.
        $achievements = Achievement::query()
            ->where('is_active', true)
            ->orWhereHas('userAchievements', fn ($query) => $query
                ->where('user_id', $user->id)
                ->whereNotNull('unlocked_at'))
            ->orderBy('id')
            ->get();
        $achievementRecords = UserAchievement::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('achievement_id');

        return view('dashboard', compact('user', 'wallet', 'total', 'completed', 'recent', 'achievements', 'achievementRecords', 'goldenApplesEarned'));
    }

    /**
     * Reset only the authenticated player's adventure progress.
     * Wallet balance and its transaction history are intentionally preserved.
     */
    public function resetProgress(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $firstLevel = Level::where('number', 1)
            ->where('is_published', true)
            ->firstOrFail();

        DB::transaction(function () use ($user, $firstLevel) {
            // Deleting sessions automatically detaches their reward claims
            // through the migration's nullOnDelete foreign key.
            GameSession::where('user_id', $user->id)->delete();

            UserLevelProgress::where('user_id', $user->id)->delete();

            \App\Models\UserAchievement::where('user_id', $user->id)->delete();

            UserLevelProgress::create([
                'user_id' => $user->id,
                'level_id' => $firstLevel->id,
                'unlocked' => true,
                'completed' => false,
                'best_score' => 0,
                'stars' => 0,
                'best_moves' => null,
                'completed_at' => null,
            ]);

            // Do not reset coins or delete the wallet ledger: progress resets
            // must not become a way to repeatedly claim starting currency.
        });

        return redirect()
            ->route('dashboard')
            ->with('status', 'Your adventure has been reset. Level 01 is ready! Your coins and wallet history were kept.');
    }

    public function levels(Request $request)
    {
        $levels = Level::where('is_published', true)->orderBy('number')->get();
        $progress = UserLevelProgress::where('user_id', $request->user()->id)
            ->whereHas('level', fn ($query) => $query->where('is_published', true))
            ->get()
            ->keyBy('level_id');

        return view('levels', compact('levels', 'progress'));
    }

    private function initialState(Level $level): array
    {
        $config = $level->config ?? [];
        $worm = [$config['wormStart'] ?? ['x' => 1, 'y' => 1]];
        if (isset($config['sideView']) && is_array($config['sideView'])) {
            return $this->initialSideViewState($config['sideView']);
        }

        return [
            'worm' => $worm,
            'apples' => array_values($config['apples'] ?? []),
            'goldenApples' => array_values($config['goldenApples'] ?? []),
            'megaApples' => array_values($config['megaApples'] ?? []),
            'mysteryApples' => array_values($config['mysteryApples'] ?? []),
            'megaApplesCollected' => 0,
            'mysteryApplesCollected' => 0,
            'growthPending' => 0,
            'keys' => array_values($config['keys'] ?? []),
            'keysCollected' => 0,
            'switches' => array_values($config['switches'] ?? []),
            'switchesActivated' => 0,
            'goldenApplesCollected' => 0,
            'moves' => 0,
            'score' => 0,
            'bonus_moves' => 0,
            'won' => false,
            'lost' => false,
            'double_score_claimed' => false,
            'double_coins_claimed' => false,
            'extra_moves_claimed' => false,
            'booster_claimed' => false,
            'rewarded_hints_used' => 0,
            'booster_active' => false,
            'checkpoint' => [
                'worm' => $worm,
                'apples' => array_values($config['apples'] ?? []),
                'goldenApples' => array_values($config['goldenApples'] ?? []),
                'megaApples' => array_values($config['megaApples'] ?? []),
                'mysteryApples' => array_values($config['mysteryApples'] ?? []),
                'megaApplesCollected' => 0,
                'mysteryApplesCollected' => 0,
                'growthPending' => 0,
                'keys' => array_values($config['keys'] ?? []),
                'keysCollected' => 0,
                'switches' => array_values($config['switches'] ?? []),
                'switchesActivated' => 0,
                'goldenApplesCollected' => 0,
                'moves' => 0,
                'score' => 0,
            ],
        ];
    }

    private function initialSideViewState(array $layout): array
    {
        $start = $layout['start'] ?? ['x' => 2, 'y' => 6, 'length' => 3];
        $length = max(2, min(6, (int) ($start['length'] ?? 3)));
        $facing = $start['facing'] ?? 'right';
        [$bodyDx, $bodyDy] = match ($facing) {
            'left' => [1, 0],
            'up' => [0, 1],
            'down' => [0, -1],
            default => [-1, 0],
        };
        $worm = [];
        for ($segment = 0; $segment < $length; $segment++) {
            $worm[] = [
                'x' => (int) $start['x'] + ($bodyDx * $segment),
                'y' => (int) $start['y'] + ($bodyDy * $segment),
            ];
        }

        return [
            'mode' => 'side-view-puzzle',
            'worm' => $worm,
            'apples' => array_values($layout['apples'] ?? []),
            'goldenApples' => array_values($layout['goldenApples'] ?? []),
            'goldenApplesCollected' => 0,
            'megaApples' => [],
            'mysteryApples' => [],
            'keys' => [],
            'blocks' => array_values($layout['blocks'] ?? []),
            'switches' => array_values($layout['switches'] ?? []),
            'switchesActivated' => 0,
            'gatesOpen' => empty($layout['gates']),
            'applesCollected' => 0,
            'growthPending' => 0,
            'moves' => 0,
            'move_limit' => max(1, (int) ($layout['moveLimit'] ?? ((int) ($layout['parMoves'] ?? 10) + 5))),
            'bonus_moves' => 0,
            'score' => 0,
            'pushes' => 0,
            'gravitySteps' => 0,
            'won' => false,
            'lost' => false,
            'double_score_claimed' => false,
            'double_coins_claimed' => false,
            'extra_moves_claimed' => false,
            'booster_claimed' => false,
            'revive_claimed' => false,
            'rewarded_hints_used' => 0,
            'hint_stage' => 0,
            'checkpoint' => [
                'worm' => $worm,
                'apples' => array_values($layout['apples'] ?? []),
                'goldenApples' => array_values($layout['goldenApples'] ?? []),
                'blocks' => array_values($layout['blocks'] ?? []),
                'switches' => array_values($layout['switches'] ?? []),
                'switchesActivated' => 0,
                'gatesOpen' => empty($layout['gates']),
                'growthPending' => 0,
                'moves' => 0,
                'score' => 0,
                'applesCollected' => 0,
                'goldenApplesCollected' => 0,
                'pushes' => 0,
                'gravitySteps' => 0,
            ],
        ];
    }

    public function play(Request $request, Level $level)
    {
        abort_unless($level->is_published, 404);
        $progress = UserLevelProgress::firstOrCreate(
            ['user_id' => $request->user()->id, 'level_id' => $level->id],
            ['unlocked' => $level->number === 1]
        );
        abort_unless($progress->unlocked, 403, 'Complete the previous level to unlock this one.');

        $session = GameSession::create([
            'user_id' => $request->user()->id,
            'level_id' => $level->id,
            'status' => 'active',
            'started_at' => now(),
            'state' => $this->initialState($level),
        ]);

        if (isset($level->config['sideView'])) {
            return view('puzzle-game', compact('level', 'session'));
        }

        return view('game', compact('level', 'session'));
    }

    public function playSession(Request $request, GameSession $session)
    {
        $this->assertOwner($request, $session);
        $session->load('level');
        abort_unless($session->level?->is_published, 404, 'This level is not part of the current release.');
        abort_unless($session->status === 'active', 409, 'This game session is not active.');

        $level = $session->level;

        return view('puzzle-game', compact('level', 'session'));
    }

    public function state(Request $request, GameSession $session)
    {
        $this->assertOwner($request, $session);
        abort_unless($session->level?->is_published, 404, 'This level is not part of the current release.');

        return response()->json(['state' => $session->state, 'status' => $session->status]);
    }

    public function move(Request $request, GameSession $session)
    {
        $this->assertOwner($request, $session);
        $data = $request->validate(['direction' => 'required|in:up,down,left,right']);

        return DB::transaction(function () use ($request, $session, $data) {
            $game = GameSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($game->level?->is_published, 404, 'This level is not part of the current release.');
            abort_unless(! isset($game->level?->config['sideView']), 409, 'This session uses the side-view puzzle engine.');
            abort_if($game->status !== 'active', 409, 'This game session is not active.');

            $state = $game->state ?? $this->initialState($game->level);
            abort_if(!empty($state['won']) || !empty($state['lost']), 409, 'This game has already ended.');
            $config = $game->level->config ?? [];
            $rows = (int) ($config['grid']['rows'] ?? $game->level->grid_rows);
            $cols = (int) ($config['grid']['cols'] ?? $game->level->grid_cols);
            $delta = [
                'up' => ['x' => 0, 'y' => -1],
                'down' => ['x' => 0, 'y' => 1],
                'left' => ['x' => -1, 'y' => 0],
                'right' => ['x' => 1, 'y' => 0],
            ][$data['direction']];

            $worm = $state['worm'] ?? [];
            abort_if(empty($worm), 422, 'Game state is invalid. Restart this level.');
            $head = $worm[0];
            $next = ['x' => $head['x'] + $delta['x'], 'y' => $head['y'] + $delta['y']];
            $state['moves'] = (int) ($state['moves'] ?? 0) + 1;
            $limit = (int) ($config['moveLimit'] ?? 40) + (int) ($state['bonus_moves'] ?? 0);

            $outside = $next['x'] < 0 || $next['y'] < 0 || $next['x'] >= $cols || $next['y'] >= $rows;
            $hitObstacle = collect($config['obstacles'] ?? [])->contains(
                fn ($obstacle) => (int) $obstacle['x'] === $next['x'] && (int) $obstacle['y'] === $next['y']
            );
            $hitThorn = collect($config['thorns'] ?? [])->contains(
                fn ($thorn) => (int) $thorn['x'] === $next['x'] && (int) $thorn['y'] === $next['y']
            );
            $hitMudPit = collect($config['mudPits'] ?? [])->contains(
                fn ($mud) => (int) $mud['x'] === $next['x'] && (int) $mud['y'] === $next['y']
            );
            $gateLocked = ! empty($state['keys']) || ! empty($state['switches']);
            $hitLockedGate = $gateLocked && collect($config['gates'] ?? [])->contains(
                fn ($gate) => (int) $gate['x'] === $next['x'] && (int) $gate['y'] === $next['y']
            );

            // Mud pits are impassable terrain, not lethal hazards. A blocked
            // attempt is refunded so players can route around the pit freely.
            if ($hitMudPit && ! $outside) {
                $state['moves'] = max(0, $state['moves'] - 1);
                $game->update(['state' => $state]);
                return response()->json([
                    'ok' => true,
                    'blocked' => true,
                    'reason' => 'A muddy pit blocks this tile. Find another route!',
                    'state' => $state,
                    'status' => 'active',
                ]);
            }

            // A locked gate blocks movement without ending the run or spending
            // a move. The player can turn around and find the key or activate the switch.
            if ($hitLockedGate && ! $outside && ! $hitObstacle) {
                $state['moves'] = max(0, $state['moves'] - 1);
                $game->update(['state' => $state]);
                return response()->json([
                    'ok' => true,
                    'blocked' => true,
                    'reason' => ! empty($state['keys']) ? 'The gate is locked. Find the key to open it!' : 'The gate is locked. Activate the switch to open it!',
                    'state' => $state,
                    'status' => 'active',
                ]);
            }

            $portal = $config['portal'] ?? null;
            $approachingPortal = $portal && (int) $portal['x'] === (int) $next['x'] && (int) $portal['y'] === (int) $next['y'];
            $allFruitCollected = empty($state['apples']) && empty($state['goldenApples'])
                && empty($state['megaApples']) && empty($state['mysteryApples'])
                && empty($state['keys']) && empty($state['switches']);
            $requiredLength = max(0, (int) ($config['mission']['requiredLength'] ?? 0));
            $currentLength = count($worm) + (int) ($state['growthPending'] ?? 0);
            if ($approachingPortal && $allFruitCollected && $currentLength < $requiredLength) {
                $state['moves'] = max(0, $state['moves'] - 1);
                $game->update(['state' => $state]);
                return response()->json([
                    'ok' => true,
                    'blocked' => true,
                    'reason' => "Grow to {$requiredLength} segments before entering the portal.",
                    'state' => $state,
                    'status' => 'active',
                ]);
            }

            // The tail cell normally vacates on a non-growth move, so moving
            // into it is legal. If that same cell contains an apple, however,
            // the worm grows and the tail does not vacate: it is a collision.
            $targetHasCollectible = collect(array_merge(
                $state['apples'] ?? [],
                $state['goldenApples'] ?? [],
                $state['megaApples'] ?? [],
                $state['mysteryApples'] ?? []
            ))->contains(
                fn ($item) => (int) $item['x'] === $next['x'] && (int) $item['y'] === $next['y']
            );

            $bodyForCollision = $targetHasCollectible
                ? $worm
                : array_slice($worm, 0, max(0, count($worm) - 1));

            $hitWorm = collect($bodyForCollision)->contains(
                fn ($part) => (int) $part['x'] === $next['x'] && (int) $part['y'] === $next['y']
            );
            $outOfMoves = $state['moves'] > $limit;

            if ($outside || $hitObstacle || $hitThorn || $hitWorm || $outOfMoves) {
                $state['lost'] = true;
                $game->update(['status' => 'failed', 'moves_used' => $state['moves'], 'state' => $state, 'finished_at' => now()]);
                $failureReason = $outOfMoves
                    ? 'Move limit reached.'
                    : ($hitThorn ? 'Thorns! Watch your step and try another route.' : 'Collision detected. Watch out for rocks and your own tail.');
                return response()->json(['ok' => true, 'failed' => true, 'reason' => $failureReason, 'state' => $state, 'status' => 'failed']);
            }

            $state['checkpoint'] = [
                'worm' => $worm,
                'apples' => $state['apples'] ?? [],
                'goldenApples' => $state['goldenApples'] ?? [],
                'megaApples' => $state['megaApples'] ?? [],
                'mysteryApples' => $state['mysteryApples'] ?? [],
                'megaApplesCollected' => (int) ($state['megaApplesCollected'] ?? 0),
                'mysteryApplesCollected' => (int) ($state['mysteryApplesCollected'] ?? 0),
                'growthPending' => (int) ($state['growthPending'] ?? 0),
                'keys' => $state['keys'] ?? [],
                'keysCollected' => (int) ($state['keysCollected'] ?? 0),
                'switches' => $state['switches'] ?? [],
                'switchesActivated' => (int) ($state['switchesActivated'] ?? 0),
                'goldenApplesCollected' => (int) ($state['goldenApplesCollected'] ?? 0),
                'moves' => $state['moves'] - 1,
                'score' => $state['score'] ?? 0,
            ];
            $appleIndex = null;
            foreach (($state['apples'] ?? []) as $index => $apple) {
                if ((int) $apple['x'] === $next['x'] && (int) $apple['y'] === $next['y']) { $appleIndex = $index; break; }
            }

            $goldenIndex = null;
            foreach (($state['goldenApples'] ?? []) as $index => $apple) {
                if ((int) $apple['x'] === $next['x'] && (int) $apple['y'] === $next['y']) { $goldenIndex = $index; break; }
            }

            $megaIndex = null;
            foreach (($state['megaApples'] ?? []) as $index => $megaApple) {
                if ((int) $megaApple['x'] === $next['x'] && (int) $megaApple['y'] === $next['y']) { $megaIndex = $index; break; }
            }

            $mysteryIndex = null;
            foreach (($state['mysteryApples'] ?? []) as $index => $mysteryApple) {
                if ((int) $mysteryApple['x'] === $next['x'] && (int) $mysteryApple['y'] === $next['y']) { $mysteryIndex = $index; break; }
            }

            $keyIndex = null;
            foreach (($state['keys'] ?? []) as $index => $key) {
                if ((int) $key['x'] === $next['x'] && (int) $key['y'] === $next['y']) { $keyIndex = $index; break; }
            }

            $switchIndex = null;
            foreach (($state['switches'] ?? []) as $index => $switch) {
                if ((int) $switch['x'] === $next['x'] && (int) $switch['y'] === $next['y']) { $switchIndex = $index; break; }
            }

            $ateApple = $appleIndex !== null || $goldenIndex !== null || $megaIndex !== null || $mysteryIndex !== null;
            $growthAdded = 0;
            $newAchievements = [];

            if ($keyIndex !== null) {
                array_splice($state['keys'], $keyIndex, 1);
                $state['keysCollected'] = (int) ($state['keysCollected'] ?? 0) + 1;
                $this->addScore($state, 25);
            }

            if ($switchIndex !== null) {
                array_splice($state['switches'], $switchIndex, 1);
                $state['switchesActivated'] = (int) ($state['switchesActivated'] ?? 0) + 1;
                $this->addScore($state, 20);
            }

            if ($appleIndex !== null) {
                array_splice($state['apples'], $appleIndex, 1);
                $points = !empty($state['booster_active']) ? 20 : 10;
                $this->addScore($state, $points);
                $growthAdded += 1;
                $state['booster_active'] = false;
                $newAchievements = array_merge(
                    $newAchievements,
                    app(AchievementService::class)->recordCounter($request->user(), 'apples')
                );
            }

            if ($goldenIndex !== null) {
                array_splice($state['goldenApples'], $goldenIndex, 1);
                $state['goldenApplesCollected'] = (int) ($state['goldenApplesCollected'] ?? 0) + 1;
                $this->addScore($state, 50);
                $growthAdded += 1;
            }

            if ($megaIndex !== null) {
                array_splice($state['megaApples'], $megaIndex, 1);
                $state['megaApplesCollected'] = (int) ($state['megaApplesCollected'] ?? 0) + 1;
                $this->addScore($state, 10);
                $growthAdded += 3;
            }

            $mysteryEffect = null;
            if ($mysteryIndex !== null) {
                $mysteryApple = $state['mysteryApples'][$mysteryIndex];
                array_splice($state['mysteryApples'], $mysteryIndex, 1);
                $state['mysteryApplesCollected'] = (int) ($state['mysteryApplesCollected'] ?? 0) + 1;
                $this->addScore($state, 10);
                $mysteryEffect = $mysteryApple['effect'] ?? 'bonus_score';
                $state['lastMysteryEffect'] = $mysteryEffect;

                if ($mysteryEffect === 'extra_growth') {
                    $growthAdded += 2;
                } elseif ($mysteryEffect === 'bonus_moves') {
                    $state['bonus_moves'] = (int) ($state['bonus_moves'] ?? 0) + 5;
                } else {
                    $this->addScore($state, 20);
                }
            }

            // Growth is server-authoritative. One segment is added immediately;
            // bonus growth remains pending and preserves the tail on future moves.
            $state['growthPending'] = (int) ($state['growthPending'] ?? 0) + $growthAdded;
            array_unshift($worm, $next);
            if ((int) $state['growthPending'] > 0) {
                $state['growthPending'] = (int) $state['growthPending'] - 1;
            } else {
                array_pop($worm);
            }
            $state['worm'] = $worm;

            $portal = $config['portal'] ?? null;
            $atPortal = $portal && $next['x'] === $portal['x'] && $next['y'] === $portal['y'];
            $requiredLength = max(0, (int) ($config['mission']['requiredLength'] ?? 0));
            $effectiveLength = count($worm) + (int) ($state['growthPending'] ?? 0);
            if ($atPortal
                && empty($state['apples'])
                && empty($state['goldenApples'])
                && empty($state['megaApples'])
                && empty($state['mysteryApples'])
                && empty($state['keys'])
                && empty($state['switches'])
                && $effectiveLength >= $requiredLength) {
                $state['won'] = true;
                $this->addScore($state, 100);
            }

            $game->update([
                'state' => $state,
                'moves_used' => $state['moves'],
                'apples_collected' => count($config['apples'] ?? []) - count($state['apples']),
                'score' => $state['score'],
            ]);

            return response()->json(['ok' => true, 'state' => $state, 'status' => $game->status, 'won' => $state['won'], 'achievements_unlocked' => $newAchievements]);
        });
    }

    public function puzzleMove(Request $request, GameSession $session)
    {
        $this->assertOwner($request, $session);
        $data = $request->validate(['direction' => 'required|in:up,down,left,right']);

        return DB::transaction(function () use ($request, $session, $data) {
            $game = GameSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($game->level?->is_published, 404, 'This level is not part of the current release.');
            abort_unless(isset($game->level?->config['sideView']), 422, 'This session does not use the side-view puzzle engine.');
            abort_if($game->status !== 'active', 409, 'This game session is not active.');

            $result = app(\App\Services\SideViewPuzzleService::class)->move($game, $data['direction']);
            if (! empty($result['collected_apple'])) {
                $newAchievements = app(AchievementService::class)->recordCounter($request->user(), 'apples');
                $result['achievements_unlocked'] = $newAchievements;
            }

            return response()->json($result);
        });
    }

    public function restart(Request $request, GameSession $session)
    {
        $this->assertOwner($request, $session);

        return DB::transaction(function () use ($request, $session) {
            $game = GameSession::query()
                ->with('level')
                ->whereKey($session->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($game->level?->is_published, 404, 'This level is not part of the current release.');
            abort_if($game->status === 'completed', 409, 'A completed level cannot be restarted in the same session.');
            $previousState = $game->state ?? [];
            $state = $this->initialState($game->level);

            // A restart resets the board, but consumed reward claims and their
            // effects remain attached to the session to prevent reward farming.
            foreach ([
                'double_score_claimed',
                'double_coins_claimed',
                'extra_moves_claimed',
                'booster_claimed',
                'booster_active',
                'revive_claimed',
                'rewarded_hints_used',
                'bonus_moves',
            ] as $persistentKey) {
                if (array_key_exists($persistentKey, $previousState)) {
                    $state[$persistentKey] = $previousState[$persistentKey];
                }
            }

            $game->update([
                'status' => 'active',
                'state' => $state,
                'score' => 0,
                'moves_used' => 0,
                'apples_collected' => 0,
                'finished_at' => null,
            ]);

            if ($request->expectsJson()) {
                return response()->json(['ok' => true, 'state' => $state, 'status' => 'active']);
            }

            return redirect()->route('game.session.play', $game);
        });
    }

    public function complete(Request $request, GameSession $session)
    {
        $this->assertOwner($request, $session);

        return DB::transaction(function () use ($request, $session) {
            $game = GameSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($game->level?->is_published, 404, 'This level is not part of the current release.');
            abort_if($game->status !== 'active', 409, 'Session is no longer active.');

            $state = $game->state ?? [];
            $config = $game->level->config ?? [];
            $sideView = $config['sideView'] ?? null;
            $requiredLength = $sideView ? 0 : max(0, (int) ($config['mission']['requiredLength'] ?? 0));
            $effectiveLength = count($state['worm'] ?? []) + (int) ($state['growthPending'] ?? 0);
            $allObjectivesComplete = ! empty($state['won'])
                && empty($state['apples'])
                && empty($state['goldenApples'])
                && empty($state['megaApples'])
                && empty($state['mysteryApples'])
                && empty($state['keys'])
                && empty($state['switches'])
                && $effectiveLength >= $requiredLength;
            abort_unless($allObjectivesComplete, 422, 'The server has not recorded a valid level completion.');

            $moves = (int) ($state['moves'] ?? 0);
            $parMoves = (int) ($sideView['parMoves'] ?? 0);
            // The base budget is stored with the session so a later catalog
            // edit cannot silently change a puzzle that a player already started.
            $baseMoveLimit = $sideView
                ? max(1, (int) ($state['move_limit'] ?? $sideView['moveLimit'] ?? ($parMoves + 5)))
                : max(1, (int) ($config['moveLimit'] ?? 40));
            $bonusMoves = max(0, (int) ($state['bonus_moves'] ?? 0));
            $moveLimit = $baseMoveLimit + $bonusMoves;
            abort_if($moves < 1 || $moves > $moveLimit, 422, 'Invalid move count.');

            $score = min(100000, max(0, (int) ($state['score'] ?? 0)));
            if ($sideView) {
                $twoStarLimit = $parMoves + intdiv(max(0, $baseMoveLimit - $parMoves), 2);
                $stars = $moves <= $parMoves ? 3 : ($moves <= $twoStarLimit ? 2 : 1);
            } else {
                $stars = $moves <= max(8, (int) ($config['moveLimit'] ?? 30) * .55)
                    ? 3
                    : ($moves <= (int) ($config['moveLimit'] ?? 30) * .8 ? 2 : 1);
            }

            $game->update([
                'status' => 'completed',
                'score' => $score,
                'moves_used' => $moves,
                'apples_collected' => $sideView
                    ? max(0, (int) ($state['applesCollected'] ?? 0)) + max(0, (int) ($state['goldenApplesCollected'] ?? 0))
                    : count($config['apples'] ?? []),
                'finished_at' => now(),
            ]);

            $userId = (int) $request->user()->id;
            $levelId = (int) $game->level_id;
            $progress = UserLevelProgress::query()->firstOrCreate([
                'user_id' => $userId,
                'level_id' => $levelId,
            ]);

            $goldenAppleTotal = count($sideView['goldenApples'] ?? $config['goldenApples'] ?? []);
            $goldenCollected = min(
                $goldenAppleTotal,
                max(0, (int) ($state['goldenApplesCollected'] ?? 0))
            );

            // This permanent record is intentionally not deleted by Reset My Progress.
            // It prevents the same level's golden-apple coin bonus being farmed after resets.
            $goldenReward = null;
            $newGoldenForThisLevel = 0;
            if ($goldenCollected > 0) {
                DB::table('user_level_rewards')->insertOrIgnore([
                    'user_id' => $userId,
                    'level_id' => $levelId,
                    'reward_code' => 'golden_apple_completion',
                    'quantity' => 0,
                    'coins_awarded' => 0,
                    'metadata' => json_encode(['source' => 'level_completion']),
                    'granted_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $goldenReward = UserLevelReward::query()
                    ->where('user_id', $userId)
                    ->where('level_id', $levelId)
                    ->where('reward_code', 'golden_apple_completion')
                    ->lockForUpdate()
                    ->firstOrFail();

                $previousGoldenBest = (int) $goldenReward->quantity;
                $newGoldenForThisLevel = max(0, $goldenCollected - $previousGoldenBest);

                if ($goldenCollected > $previousGoldenBest) {
                    $goldenReward->update([
                        'quantity' => $goldenCollected,
                        'granted_at' => now(),
                    ]);
                }
            }

            $progress->update([
                'unlocked' => true,
                'completed' => true,
                'golden_apples_collected' => max((int) $progress->golden_apples_collected, $goldenCollected),
                'best_score' => max((int) $progress->best_score, $score),
                'stars' => max((int) $progress->stars, $stars),
                'best_moves' => $progress->best_moves ? min((int) $progress->best_moves, $moves) : $moves,
                'completed_at' => now(),
            ]);

            $nextLevel = Level::query()
                ->where('is_published', true)
                ->where('number', $game->level->number + 1)
                ->first();

            if ($nextLevel) {
                UserLevelProgress::query()->firstOrCreate(
                    ['user_id' => $userId, 'level_id' => $nextLevel->id],
                    ['unlocked' => true]
                );
            }

            // Completion coins are paid only on the first successful clear of
            // each level. Replays remain playable and can improve best score/stars,
            // but cannot be used to farm the base coin reward.
            $levelRewardCreated = DB::table('user_level_rewards')->insertOrIgnore([
                'user_id' => $userId,
                'level_id' => $levelId,
                'reward_code' => 'level_completion',
                'quantity' => 1,
                'coins_awarded' => 0,
                'metadata' => json_encode(['source' => 'first_level_completion']),
                'granted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]) === 1;

            $levelRewardRecord = UserLevelReward::query()
                ->where('user_id', $userId)
                ->where('level_id', $levelId)
                ->where('reward_code', 'level_completion')
                ->lockForUpdate()
                ->firstOrFail();

            $doubleCoinsMultiplier = ! empty($state['double_coins_claimed']) ? 2 : 1;
            $baseReward = 50 + ($stars * 10);
            $levelRewardCoins = $levelRewardCreated ? ($baseReward * $doubleCoinsMultiplier) : 0;
            $goldenBonusCoins = $newGoldenForThisLevel * 20 * $doubleCoinsMultiplier;

            $wallet = app(WalletService::class)->getOrCreate($request->user());

            if ($levelRewardCoins > 0) {
                $wallet->increment('coins', $levelRewardCoins);
                $wallet->refresh();

                WalletTransaction::query()->create([
                    'user_id' => $userId,
                    'type' => 'level_reward',
                    'amount' => $levelRewardCoins,
                    'balance_after' => $wallet->coins,
                    'reference' => 'level-completion-' . $userId . '-' . $levelId,
                    'description' => 'First level completion reward' . ($doubleCoinsMultiplier === 2 ? ' (doubled)' : ''),
                    'metadata' => [
                        'level_id' => $levelId,
                        'stars' => $stars,
                        'double_coins' => $doubleCoinsMultiplier === 2,
                    ],
                ]);

                $levelRewardRecord->update([
                    'coins_awarded' => $levelRewardCoins,
                    'granted_at' => now(),
                ]);
            }

            if ($goldenBonusCoins > 0) {
                $goldenReference = 'golden-apple-' . $userId . '-' . $levelId . '-up-to-' . $goldenCollected;

                // A unique per-player/per-level reference is the final duplicate-credit guard.
                if (! WalletTransaction::query()->where('reference', $goldenReference)->exists()) {
                    $wallet->increment('coins', $goldenBonusCoins);
                    $wallet->refresh();

                    WalletTransaction::query()->create([
                        'user_id' => $userId,
                        'type' => 'golden_apple_reward',
                        'amount' => $goldenBonusCoins,
                        'balance_after' => $wallet->coins,
                        'reference' => $goldenReference,
                        'description' => 'Golden Apple completion bonus' . ($doubleCoinsMultiplier === 2 ? ' (doubled)' : ''),
                        'metadata' => [
                            'level_id' => $levelId,
                            'golden_apples' => $newGoldenForThisLevel,
                            'double_coins' => $doubleCoinsMultiplier === 2,
                        ],
                    ]);

                    if ($goldenReward) {
                        $goldenReward->increment('coins_awarded', $goldenBonusCoins);
                    }
                } else {
                    $goldenBonusCoins = 0;
                }
            }

            $newAchievements = app(AchievementService::class)->syncCompletion($request->user());
            $totalCoinsEarned = $levelRewardCoins + $goldenBonusCoins;

            return response()->json([
                'ok' => true,
                'score' => $score,
                'stars' => $stars,
                'coins_earned' => $totalCoinsEarned,
                'level_coins_earned' => $levelRewardCoins,
                'golden_apples_collected' => $goldenCollected,
                'golden_bonus_coins' => $goldenBonusCoins,
                'achievements_unlocked' => $newAchievements,
                'message' => 'Level complete!',
            ]);
        });
    }

    public function fail(Request $request, GameSession $session)
    {
        $this->assertOwner($request, $session);
        abort_unless($session->level?->is_published, 404, 'This level is not part of the current release.');
        if ($session->status === 'active') $session->update(['status' => 'failed', 'finished_at' => now()]);
        return response()->json(['ok' => true, 'revive_available' => $session->revives_used < 1]);
    }

    public function hint(Request $request, GameSession $session)
    {
        $this->assertOwner($request, $session);

        return DB::transaction(function () use ($request, $session) {
            $game = GameSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($game->level?->is_published, 404, 'This level is not part of the current release.');
            abort_if($game->status !== 'active', 409, 'This game session is not active.');

            if (isset($game->level?->config['sideView'])) {
                $hint = app(SideViewHintService::class)->next($game);
                if (! $hint['exhausted']) {
                    $game->increment('hints_used');
                }

                return response()->json([
                    'ok' => true,
                    'hint' => $hint['hint'],
                    'stage' => $hint['stage'],
                    'total' => $hint['total'],
                    'exhausted' => $hint['exhausted'],
                    'has_more' => $hint['has_more'],
                    'repeatable' => $hint['repeatable'] ?? false,
                    'provider' => 'authored_progressive_hints',
                ]);
            }

            $hint = app(GameHintService::class)->suggest($game);
            $game->increment('hints_used');

            return response()->json([
                'ok' => true,
                'hint' => $hint['hint'],
                'direction' => $hint['direction'],
                'target' => $hint['target'],
                'provider' => 'server_pathfinder',
            ]);
        });
    }

    private function addScore(array &$state, int $basePoints): void
    {
        $multiplier = ! empty($state['double_score_claimed']) ? 2 : 1;
        $current = max(0, (int) ($state['score'] ?? 0));
        $state['score'] = min(100000, $current + ($basePoints * $multiplier));
    }

    private function assertOwner(Request $request, GameSession $session): void
    {
        abort_unless((int) $session->user_id === (int) $request->user()->id, 403);
    }
}
