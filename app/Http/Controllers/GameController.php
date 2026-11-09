<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\Level;
use App\Models\UserLevelProgress;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Achievement;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $wallet = $user->wallet()->firstOrCreate([], ['coins' => 100]);
        $total = Level::where('is_published', true)->count();

        // Dashboard statistics must reflect only levels available in this release.
        $publishedProgress = UserLevelProgress::with('level')
            ->where('user_id', $user->id)
            ->whereHas('level', fn ($query) => $query->where('is_published', true))
            ->get();
        $user->setRelation('progress', $publishedProgress);
        $completed = $publishedProgress->where('completed', true)->count();
        $goldenApplesEarned = (int) $publishedProgress->where('completed', true)->sum('golden_apples_collected');

        $recent = $user->gameSessions()
            ->whereHas('level', fn ($query) => $query->where('is_published', true))
            ->latest()
            ->take(5)
            ->with('level')
            ->get();

        $achievements = Achievement::query()->orderBy('id')->get();
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
        return [
            'worm' => $worm,
            'apples' => array_values($config['apples'] ?? []),
            'goldenApples' => array_values($config['goldenApples'] ?? []),
            'goldenApplesCollected' => 0,
            'moves' => 0,
            'score' => 0,
            'bonus_moves' => 0,
            'won' => false,
            'lost' => false,
            'double_score_claimed' => false,
            'double_coins_claimed' => false,
            'booster_active' => false,
            'checkpoint' => [
                'worm' => $worm,
                'apples' => array_values($config['apples'] ?? []),
                'goldenApples' => array_values($config['goldenApples'] ?? []),
                'goldenApplesCollected' => 0,
                'moves' => 0,
                'score' => 0,
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

        return view('game', compact('level', 'session'));
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
            $hitObstacle = collect($config['obstacles'] ?? [])->contains(fn ($o) => $o['x'] === $next['x'] && $o['y'] === $next['y']);
            $hitWorm = collect($worm)->slice(0, max(0, count($worm) - 1))->contains(fn ($part) => $part['x'] === $next['x'] && $part['y'] === $next['y']);
            $outOfMoves = $state['moves'] > $limit;

            if ($outside || $hitObstacle || $hitWorm || $outOfMoves) {
                $state['lost'] = true;
                $game->update(['status' => 'failed', 'moves_used' => $state['moves'], 'state' => $state, 'finished_at' => now()]);
                return response()->json(['ok' => true, 'failed' => true, 'reason' => $outOfMoves ? 'Move limit reached.' : 'Collision detected.', 'state' => $state, 'status' => 'failed']);
            }

            $state['checkpoint'] = [
                'worm' => $worm,
                'apples' => $state['apples'] ?? [],
                'goldenApples' => $state['goldenApples'] ?? [],
                'goldenApplesCollected' => (int) ($state['goldenApplesCollected'] ?? 0),
                'moves' => $state['moves'] - 1,
                'score' => $state['score'] ?? 0,
            ];
            array_unshift($worm, $next);
            $appleIndex = null;
            foreach (($state['apples'] ?? []) as $index => $apple) {
                if ((int) $apple['x'] === $next['x'] && (int) $apple['y'] === $next['y']) { $appleIndex = $index; break; }
            }

            $goldenIndex = null;
            foreach (($state['goldenApples'] ?? []) as $index => $apple) {
                if ((int) $apple['x'] === $next['x'] && (int) $apple['y'] === $next['y']) { $goldenIndex = $index; break; }
            }

            $ateApple = $appleIndex !== null || $goldenIndex !== null;
            $newAchievements = [];

            if ($appleIndex !== null) {
                array_splice($state['apples'], $appleIndex, 1);
                $points = !empty($state['booster_active']) ? 20 : 10;
                $state['score'] = (int) ($state['score'] ?? 0) + $points;
                $state['booster_active'] = false;
                $newAchievements = array_merge(
                    $newAchievements,
                    app(AchievementService::class)->recordCounter($request->user(), 'apples')
                );
            }

            if ($goldenIndex !== null) {
                array_splice($state['goldenApples'], $goldenIndex, 1);
                $state['goldenApplesCollected'] = (int) ($state['goldenApplesCollected'] ?? 0) + 1;
                $state['score'] = (int) ($state['score'] ?? 0) + 50;
            }

            if (! $ateApple) {
                array_pop($worm);
            }
            $state['worm'] = $worm;

            $portal = $config['portal'] ?? null;
            $atPortal = $portal && $next['x'] === $portal['x'] && $next['y'] === $portal['y'];
            if ($atPortal && empty($state['apples'])) {
                $state['won'] = true;
                $state['score'] = (int) ($state['score'] ?? 0) + 100;
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

    public function restart(Request $request, GameSession $session)
    {
        $this->assertOwner($request, $session);
        abort_unless($session->level?->is_published, 404, 'This level is not part of the current release.');
        abort_if($session->status === 'completed', 409, 'A completed level cannot be restarted in the same session.');
        $state = $this->initialState($session->level);
        $session->update(['status' => 'active', 'state' => $state, 'score' => 0, 'moves_used' => 0, 'apples_collected' => 0, 'finished_at' => null]);
        return response()->json(['ok' => true, 'state' => $state, 'status' => 'active']);
    }

    public function complete(Request $request, GameSession $session)
    {
        $this->assertOwner($request, $session);
        return DB::transaction(function () use ($request, $session) {
            $game = GameSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($game->level?->is_published, 404, 'This level is not part of the current release.');
            abort_if($game->status !== 'active', 409, 'Session is no longer active.');
            $state = $game->state ?? [];
            $config = $game->level->config ?? [];
            abort_unless(!empty($state['won']) && empty($state['apples']), 422, 'The server has not recorded a valid level completion.');

            $moves = (int) ($state['moves'] ?? 0);
            $moveLimit = (int) ($config['moveLimit'] ?? 40) + (int) ($state['bonus_moves'] ?? 0);
            abort_if($moves < 1 || $moves > $moveLimit, 422, 'Invalid move count.');
            $score = min(5000, (int) ($state['score'] ?? 0));
            $stars = $moves <= max(8, (int) ($config['moveLimit'] ?? 30) * .55) ? 3 : ($moves <= (int) ($config['moveLimit'] ?? 30) * .8 ? 2 : 1);

            $game->update(['status' => 'completed', 'score' => $score, 'moves_used' => $moves, 'apples_collected' => count($config['apples'] ?? []), 'finished_at' => now()]);
            $progress = UserLevelProgress::firstOrCreate(['user_id' => $request->user()->id, 'level_id' => $game->level_id]);
            $goldenCollected = min(
                count($config['goldenApples'] ?? []),
                max(0, (int) ($state['goldenApplesCollected'] ?? 0))
            );
            $previousGoldenBest = (int) ($progress->golden_apples_collected ?? 0);
            $newGoldenForThisLevel = max(0, $goldenCollected - $previousGoldenBest);

            $progress->update([
                'unlocked' => true, 'completed' => true,
                'golden_apples_collected' => max($previousGoldenBest, $goldenCollected),
                'best_score' => max((int) $progress->best_score, $score),
                'stars' => max((int) $progress->stars, $stars),
                'best_moves' => $progress->best_moves ? min($progress->best_moves, $moves) : $moves,
                'completed_at' => now(),
            ]);
            $nextLevel = Level::where('is_published', true)->where('number', $game->level->number + 1)->first();
            if ($nextLevel) UserLevelProgress::firstOrCreate(['user_id' => $request->user()->id, 'level_id' => $nextLevel->id], ['unlocked' => true]);

            $goldenBonusCoins = $newGoldenForThisLevel * 20;
            $baseReward = 50 + ($stars * 10) + $goldenBonusCoins;
            $reward = !empty($state['double_coins_claimed']) ? $baseReward * 2 : $baseReward;
            $wallet = Wallet::where('user_id', $request->user()->id)->lockForUpdate()->firstOrCreate(['user_id' => $request->user()->id], ['coins' => 100]);
            $wallet->increment('coins', $reward);
            $wallet->refresh();
            WalletTransaction::create([
                'user_id' => $request->user()->id, 'type' => 'level_reward', 'amount' => $reward,
                'balance_after' => $wallet->coins, 'reference' => 'level-' . $game->id,
                'description' => 'Level completion reward' . ($goldenBonusCoins > 0 ? ' including golden apple bonus' : '') . ($reward > $baseReward ? ' (doubled)' : ''),
                'metadata' => ['golden_apples_collected' => $goldenCollected, 'golden_bonus_coins' => $goldenBonusCoins],
            ]);

            $newAchievements = app(AchievementService::class)->syncCompletion($request->user());

            return response()->json([
                'ok' => true, 'score' => $score, 'stars' => $stars, 'coins_earned' => $reward,
                'golden_apples_collected' => $goldenCollected, 'golden_bonus_coins' => $goldenBonusCoins,
                'achievements_unlocked' => $newAchievements, 'message' => 'Level complete!',
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
        abort_unless($session->level?->is_published, 404, 'This level is not part of the current release.');
        abort_if($session->status !== 'active', 409, 'This game session is not active.');
        $state = $session->state ?? [];
        $head = $state['worm'][0] ?? null;
        $target = $state['apples'][0] ?? ($session->level->config['portal'] ?? null);
        $hint = 'Collect all apples, then reach the portal.';
        if ($head && $target) {
            $dx = $target['x'] - $head['x']; $dy = $target['y'] - $head['y'];
            $hint = abs($dx) >= abs($dy) ? ($dx >= 0 ? 'Try moving right toward your next target.' : 'Try moving left toward your next target.') : ($dy >= 0 ? 'Try moving down toward your next target.' : 'Try moving up toward your next target.');
        }
        $session->increment('hints_used');
        return response()->json(['ok' => true, 'hint' => $hint, 'provider' => 'development_mock']);
    }

    private function assertOwner(Request $request, GameSession $session): void
    {
        abort_unless((int) $session->user_id === (int) $request->user()->id, 403);
    }
}
