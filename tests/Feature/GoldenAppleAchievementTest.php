<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\GameSession;
use App\Models\Level;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserLevelProgress;
use App\Models\UserLevelReward;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AchievementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoldenAppleAchievementTest extends TestCase
{
    use RefreshDatabase;

    public function test_golden_apples_are_on_the_safe_route_and_away_from_other_items(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (Level::where('is_published', true)->get() as $level) {
            $config = $level->config;
            $golden = $config['goldenApples'] ?? [];
            $routeKeys = collect($config['plannedRoute'])->map(fn ($p) => $p['x'] . ':' . $p['y']);
            $normalKeys = collect($config['apples'])->map(fn ($p) => $p['x'] . ':' . $p['y']);
            $obstacleKeys = collect($config['obstacles'])->map(fn ($p) => $p['x'] . ':' . $p['y']);

            foreach ($golden as $point) {
                $key = $point['x'] . ':' . $point['y'];
                $this->assertTrue($routeKeys->contains($key), "Golden apple for level {$level->number} is not on its route.");
                $this->assertFalse($normalKeys->contains($key), "Golden apple overlaps a normal apple in level {$level->number}.");
                $this->assertFalse($obstacleKeys->contains($key), "Golden apple overlaps an obstacle in level {$level->number}.");
            }

            $this->assertSame($level->number % 5 === 0 ? 1 : 0, count($golden));
        }
    }

    public function test_collecting_golden_apple_adds_bonus_score_and_completion_coins(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create(['name' => 'Golden Player', 'email' => 'golden@example.test', 'password' => 'secret-password']);
        $level = $this->useLegacyBoard(Level::where('number', 5)->firstOrFail());
        UserLevelProgress::create(['user_id' => $player->id, 'level_id' => $level->id, 'unlocked' => true]);
        Wallet::create(['user_id' => $player->id, 'coins' => 100]);

        $config = $level->config;
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => [
                'worm' => [$config['wormStart']],
                'apples' => $config['apples'],
                'goldenApples' => $config['goldenApples'] ?? [],
                'goldenApplesCollected' => 0,
                'moves' => 0,
                'score' => 0,
                'bonus_moves' => 0,
                'won' => false,
                'lost' => false,
                'checkpoint' => [
                    'worm' => [$config['wormStart']],
                    'apples' => $config['apples'],
                    'goldenApples' => $config['goldenApples'] ?? [],
                    'goldenApplesCollected' => 0,
                    'moves' => 0,
                    'score' => 0,
                ],
            ],
        ]);

        $this->actingAs($player);
        $route = $config['plannedRoute'];
        $collectedState = null;

        for ($i = 1; $i < count($route); $i++) {
            $previous = $route[$i - 1];
            $next = $route[$i];
            $direction = $next['x'] > $previous['x'] ? 'right'
                : ($next['x'] < $previous['x'] ? 'left'
                : ($next['y'] > $previous['y'] ? 'down' : 'up'));

            $response = $this->postJson(route('game.move', $session), ['direction' => $direction])->assertOk();
            $collectedState = $response->json('state');
        }

        $this->assertSame(1, (int) ($collectedState['goldenApplesCollected'] ?? 0));
        $this->assertSame([], $collectedState['goldenApples'] ?? []);
        $this->assertGreaterThanOrEqual(50, (int) $collectedState['score']);
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $player->id,
            'achievement_id' => Achievement::where('code', 'first_apple')->value('id'),
        ]);

        $completion = $this->postJson(route('game.complete', $session))
            ->assertOk()
            ->assertJsonPath('golden_apples_collected', 1)
            ->assertJsonPath('golden_bonus_coins', 20);

        // The route redesign can change the move count, which can legitimately
        // change the star tier. Derive the first-clear reward from the stars
        // returned by the completion endpoint instead of hard-coding 80 coins.
        $expectedLevelCoins = 50 + ((int) $completion->json('stars') * 10);
        $this->assertSame($expectedLevelCoins, (int) $completion->json('level_coins_earned'));

        $this->assertDatabaseHas('user_level_progress', [
            'user_id' => $player->id,
            'level_id' => $level->id,
            'golden_apples_collected' => 1,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $player->id,
            'type' => 'level_reward',
            'amount' => $expectedLevelCoins,
            'reference' => 'level-completion-' . $player->id . '-' . $level->id,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $player->id,
            'type' => 'golden_apple_reward',
            'amount' => 20,
            'reference' => 'golden-apple-' . $player->id . '-' . $level->id . '-up-to-1',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $player->id,
            'type' => 'achievement_reward',
            'amount' => 10,
            'reference' => 'achievement-' . $player->id . '-' . Achievement::where('code', 'first_apple')->value('id'),
        ]);
    }

    public function test_golden_hunter_achievement_counts_unique_completed_levels_and_pays_once(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create(['name' => 'Badge Player', 'email' => 'badge@example.test', 'password' => 'secret-password']);
        Wallet::create(['user_id' => $player->id, 'coins' => 100]);

        foreach ([5, 10, 15, 20, 25] as $number) {
            $level = Level::where('number', $number)->firstOrFail();
            UserLevelProgress::create([
                'user_id' => $player->id,
                'level_id' => $level->id,
                'unlocked' => true,
                'completed' => true,
                'stars' => 0,
                'golden_apples_collected' => 1,
            ]);
            UserLevelReward::create([
                'user_id' => $player->id,
                'level_id' => $level->id,
                'reward_code' => 'golden_apple_completion',
                'quantity' => 1,
                'coins_awarded' => 20,
            ]);
        }

        $service = app(AchievementService::class);
        $first = $service->syncCompletion($player);
        $second = $service->syncCompletion($player);

        $this->assertContains('golden_hunter', collect($first)->pluck('code')->all());
        $this->assertCount(0, $second);
        $this->assertSame(190, (int) Wallet::where('user_id', $player->id)->value('coins'));
        $this->assertSame(1, WalletTransaction::where('reference', 'achievement-' . $player->id . '-' . Achievement::where('code', 'golden_hunter')->value('id'))->count());
    }

    public function test_completion_unlocks_level_and_star_achievements(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create(['name' => 'Progress Player', 'email' => 'progress@example.test', 'password' => 'secret-password']);
        $levels = Level::where('is_published', true)->orderBy('number')->take(5)->get();
        foreach ($levels as $level) {
            UserLevelProgress::create([
                'user_id' => $player->id,
                'level_id' => $level->id,
                'unlocked' => true,
                'completed' => true,
                'stars' => 2,
                'best_score' => 100,
            ]);
        }

        $unlocked = app(AchievementService::class)->syncCompletion($player);
        $codes = collect($unlocked)->pluck('code')->all();

        $this->assertContains('five_levels', $codes);
        $this->assertContains('star_collector', $codes);
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $player->id,
            'achievement_id' => Achievement::where('code', 'five_levels')->value('id'),
        ]);
    }
    public function test_first_rewarded_action_unlocks_reward_explorer_and_pays_once(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create(['name' => 'Reward Explorer', 'email' => 'explorer@example.test', 'password' => 'secret-password']);
        $level = Level::where('number', 1)->firstOrFail();
        Wallet::create(['user_id' => $player->id, 'coins' => 100]);
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => [
                'worm' => [$level->config['wormStart']],
                'apples' => $level->config['apples'],
                'goldenApples' => $level->config['goldenApples'] ?? [],
                'goldenApplesCollected' => 0,
                'moves' => 0,
                'score' => 0,
            ],
        ]);

        $response = $this->actingAs($player)->postJson(route('game.reward', $session), [
            'action' => 'hint',
            'claim_token' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertOk();

        $response->assertJsonPath('achievements_unlocked.0.code', 'ad_explorer');
        $this->assertSame(115, (int) Wallet::where('user_id', $player->id)->value('coins'));

        $this->actingAs($player)->postJson(route('game.reward', $session), [
            'action' => 'hint',
            'claim_token' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertOk();

        $this->assertSame(115, (int) Wallet::where('user_id', $player->id)->value('coins'));
        $this->assertSame(1, WalletTransaction::where('user_id', $player->id)->where('type', 'achievement_reward')->count());
    }

    public function test_replaying_a_completed_level_does_not_pay_base_or_golden_coin_rewards_again(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create(['name' => 'Replay Player', 'email' => 'replay@example.test', 'password' => 'secret-password']);
        $level = Level::where('number', 5)->firstOrFail();
        Wallet::create(['user_id' => $player->id, 'coins' => 100]);
        UserLevelProgress::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'unlocked' => true,
        ]);

        $this->actingAs($player);
        $createWinningSession = function () use ($player, $level) {
            return GameSession::create([
                'user_id' => $player->id,
                'level_id' => $level->id,
                'status' => 'active',
                'state' => [
                    'won' => true,
                    'lost' => false,
                    'apples' => [],
                    'goldenApplesCollected' => 1,
                    'moves' => 1,
                    'score' => 150,
                    'bonus_moves' => 0,
                ],
            ]);
        };

        $firstSession = $createWinningSession();
        $this->postJson(route('game.complete', $firstSession))
            ->assertOk()
            ->assertJsonPath('golden_bonus_coins', 20);

        // Resetting visible progress must not erase the permanent per-level reward record.
        $this->post(route('account.reset-progress'), ['password' => 'secret-password'])
            ->assertRedirect(route('dashboard'));
        UserLevelProgress::firstOrCreate([
            'user_id' => $player->id,
            'level_id' => $level->id,
        ], ['unlocked' => true]);

        $secondSession = $createWinningSession();
        $this->postJson(route('game.complete', $secondSession))
            ->assertOk()
            ->assertJsonPath('coins_earned', 0)
            ->assertJsonPath('level_coins_earned', 0)
            ->assertJsonPath('golden_bonus_coins', 0);

        // The first clear paid the base and Golden Apple rewards. Replaying after
        // a progress reset pays neither reward again.
        $this->assertSame(200, (int) Wallet::where('user_id', $player->id)->value('coins'));
        $this->assertDatabaseHas('wallet_transactions', [
            'reference' => 'level-completion-' . $player->id . '-' . $level->id,
            'amount' => 80,
        ]);
        $this->assertDatabaseMissing('wallet_transactions', [
            'reference' => 'level-' . $secondSession->id,
        ]);
        $this->assertDatabaseHas('user_level_rewards', [
            'user_id' => $player->id,
            'level_id' => $level->id,
            'reward_code' => 'level_completion',
            'quantity' => 1,
            'coins_awarded' => 80,
        ]);
        $this->assertSame(1, WalletTransaction::where(
            'reference',
            'golden-apple-' . $player->id . '-' . $level->id . '-up-to-1'
        )->count());
        $this->assertDatabaseHas('user_level_rewards', [
            'user_id' => $player->id,
            'level_id' => $level->id,
            'reward_code' => 'golden_apple_completion',
            'quantity' => 1,
        ]);
    }


    public function test_double_score_applies_to_points_collected_after_claim(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create(['name' => 'Double Score', 'email' => 'double-score@example.test', 'password' => 'secret-password']);
        $level = $this->useLegacyBoard(Level::where('number', 1)->firstOrFail());
        UserLevelProgress::create(['user_id' => $player->id, 'level_id' => $level->id, 'unlocked' => true]);
        $config = $level->config;
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => [
                'worm' => [$config['wormStart']],
                'apples' => $config['apples'],
                'goldenApples' => [],
                'goldenApplesCollected' => 0,
                'moves' => 0,
                'score' => 10,
                'bonus_moves' => 0,
                'won' => false,
                'lost' => false,
            ],
        ]);

        $this->actingAs($player)->postJson(route('game.reward', $session), [
            'action' => 'double_score',
            'claim_token' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertOk();

        foreach (array_slice($config['plannedRoute'], 1) as $index => $next) {
            $previous = $config['plannedRoute'][$index];
            $direction = $next['x'] > $previous['x'] ? 'right'
                : ($next['x'] < $previous['x'] ? 'left'
                : ($next['y'] > $previous['y'] ? 'down' : 'up'));
            $response = $this->postJson(route('game.move', $session), ['direction' => $direction])->assertOk();
        }

        $this->assertTrue((bool) $response->json('won'));
        $this->assertSame(240, (int) $response->json('state.score'));
        $this->postJson(route('game.complete', $session))->assertOk()->assertJsonPath('score', 240);
    }

    public function test_rewarded_hint_returns_a_safe_direction_and_is_limited_per_session(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create(['name' => 'Hint Player', 'email' => 'hint-player@example.test', 'password' => 'secret-password']);
        $level = Level::where('number', 1)->firstOrFail();
        $config = $level->config;
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => [
                'worm' => [$config['wormStart']],
                'apples' => $config['apples'],
                'goldenApples' => [],
                'goldenApplesCollected' => 0,
                'moves' => 0,
                'score' => 0,
                'won' => false,
                'lost' => false,
            ],
        ]);

        $this->actingAs($player);
        $first = $this->postJson(route('game.reward', $session), [
            'action' => 'hint',
            'claim_token' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertOk();

        // Restarting the board must not reset the session's rewarded-hint allowance.
        $this->postJson(route('game.restart', $session))
            ->assertOk()
            ->assertJsonPath('state.rewarded_hints_used', 1);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson(route('game.reward', $session), [
                'action' => 'hint',
                'claim_token' => (string) \Illuminate\Support\Str::uuid(),
            ])->assertOk();
        }

        $this->assertNotEmpty($first->json('hint'));
        $this->assertContains($first->json('hint_direction'), ['up', 'down', 'left', 'right']);
        $this->assertSame(3, (int) $session->fresh()->state['rewarded_hints_used']);

        $this->postJson(route('game.reward', $session), [
            'action' => 'hint',
            'claim_token' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertStatus(422);
    }

    public function test_double_coins_doubles_the_level_completion_payout(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create(['name' => 'Double Coins', 'email' => 'double-coins@example.test', 'password' => 'secret-password']);
        $level = Level::where('number', 6)->firstOrFail();
        UserLevelProgress::create(['user_id' => $player->id, 'level_id' => $level->id, 'unlocked' => true]);
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => ['won' => true, 'lost' => false, 'apples' => [], 'moves' => 1, 'score' => 120, 'double_coins_claimed' => false],
        ]);

        $this->actingAs($player)->postJson(route('game.reward', $session), [
            'action' => 'double_coins',
            'claim_token' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertOk();

        $this->postJson(route('game.complete', $session))
            ->assertOk()
            ->assertJsonPath('coins_earned', 160);

        $this->assertSame(275, (int) Wallet::where('user_id', $player->id)->value('coins')); // 100 opening + 15 Reward Explorer + 160 doubled level reward
        $this->assertDatabaseHas('wallet_transactions', [
            'reference' => 'level-completion-' . $player->id . '-' . $level->id,
            'amount' => 160,
        ]);
    }


    /** Preserve legacy movement coverage without weakening the new side-view route guard. */
    private function useLegacyBoard(Level $level): Level
    {
        $config = $level->config;
        unset($config['sideView'], $config['boardMode'], $config['world']);
        $level->forceFill(['config' => $config])->save();

        return $level->fresh();
    }
}
