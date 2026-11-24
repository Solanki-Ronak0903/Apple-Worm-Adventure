<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Level;
use App\Models\RewardClaim;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RewardSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_cannot_claim_a_reward_for_another_players_session(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::create([
            'name' => 'Session Owner',
            'email' => 'session-owner@example.test',
            'password' => 'secret-password',
        ]);
        $otherPlayer = User::create([
            'name' => 'Other Player',
            'email' => 'other-player@example.test',
            'password' => 'secret-password',
        ]);
        $level = Level::where('number', 1)->firstOrFail();

        $session = GameSession::create([
            'user_id' => $owner->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => ['score' => 10, 'apples' => [], 'worm' => [['x' => 1, 'y' => 6]]],
        ]);

        $this->actingAs($otherPlayer)
            ->postJson(route('game.reward', $session), [
                'action' => 'double_score',
                'claim_token' => (string) Str::uuid(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('reward_claims', 0);
    }

    public function test_reward_claim_token_cannot_be_replayed(): void
    {
        $this->seed(DatabaseSeeder::class);

        $player = User::create([
            'name' => 'Reward Player',
            'email' => 'reward-player@example.test',
            'password' => 'secret-password',
        ]);
        $level = Level::where('number', 1)->firstOrFail();
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'score' => 40,
            'state' => [
                'score' => 40,
                'apples' => $level->config['apples'],
                'worm' => [$level->config['wormStart']],
                'double_score_claimed' => false,
            ],
        ]);
        $token = (string) Str::uuid();

        $this->actingAs($player)
            ->postJson(route('game.reward', $session), [
                'action' => 'double_score',
                'claim_token' => $token,
            ])
            ->assertOk();

        $this->actingAs($player)
            ->postJson(route('game.reward', $session), [
                'action' => 'double_score',
                'claim_token' => $token,
            ])
            ->assertStatus(409);

        $this->assertSame(1, RewardClaim::where('claim_token', $token)->count());
        $this->assertSame(80, (int) $session->fresh()->state['score']);
    }

    public function test_level_completion_credits_wallet_once_and_writes_a_ledger_entry(): void
    {
        $this->seed(DatabaseSeeder::class);

        $player = User::create([
            'name' => 'Winner',
            'email' => 'winner@example.test',
            'password' => 'secret-password',
        ]);
        $level = Level::where('number', 1)->firstOrFail();
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => [
                'won' => true,
                'lost' => false,
                'apples' => [],
                'moves' => 1,
                'score' => 100,
            ],
        ]);

        $this->actingAs($player)
            ->postJson(route('game.complete', $session))
            ->assertOk()
            ->assertJsonPath('coins_earned', 80);

        $this->actingAs($player)
            ->postJson(route('game.complete', $session))
            ->assertStatus(409);

        $this->assertSame(180, (int) Wallet::where('user_id', $player->id)->value('coins'));
        // The test database also contains the seeded admin's opening-balance entry.
        // Assert this player's ledger only: opening balance + first-clear reward.
        $this->assertSame(
            2,
            \App\Models\WalletTransaction::query()
                ->where('user_id', $player->id)
                ->count()
        );
        $this->assertDatabaseHas('wallet_transactions', ['reference' => 'wallet-opening-' . $player->id, 'amount' => 100]);
        $this->assertDatabaseHas('wallet_transactions', [
            'reference' => 'level-completion-' . $player->id . '-' . $level->id,
            'amount' => 80,
        ]);
        $this->assertDatabaseHas('user_level_rewards', [
            'user_id' => $player->id,
            'level_id' => $level->id,
            'reward_code' => 'level_completion',
            'quantity' => 1,
            'coins_awarded' => 80,
        ]);
    }

    public function test_one_time_power_up_remains_consumed_after_restart(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create([
            'name' => 'Restart Reward Player',
            'email' => 'restart-reward@example.test',
            'password' => 'secret-password',
        ]);
        $level = Level::where('number', 6)->firstOrFail();
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => [
                'worm' => [$level->config['wormStart']],
                'apples' => $level->config['apples'],
                'goldenApples' => [],
                'goldenApplesCollected' => 0,
                'moves' => 0,
                'score' => 0,
                'bonus_moves' => 0,
                'extra_moves_claimed' => false,
            ],
        ]);

        $this->actingAs($player)->postJson(route('game.reward', $session), [
            'action' => 'extra_moves',
            'claim_token' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('state.bonus_moves', 5);

        $this->postJson(route('game.restart', $session))
            ->assertOk()
            ->assertJsonPath('state.bonus_moves', 5)
            ->assertJsonPath('state.extra_moves_claimed', true);

        $this->postJson(route('game.reward', $session), [
            'action' => 'extra_moves',
            'claim_token' => (string) Str::uuid(),
        ])->assertStatus(422);

        $this->assertSame(1, RewardClaim::where('game_session_id', $session->id)
            ->where('action', 'extra_moves')
            ->count());
    }

    public function test_optional_reward_assists_unlock_after_the_tutorial_world(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create([
            'name' => 'Optional Rewards',
            'email' => 'optional-rewards@example.test',
            'password' => 'secret-password',
        ]);
        $tutorialLevel = Level::query()->where('number', 1)->firstOrFail();
        $tutorialSession = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $tutorialLevel->id,
            'status' => 'active',
            'state' => ['score' => 0, 'moves' => 0, 'bonus_moves' => 0, 'apples' => [], 'worm' => [['x' => 2, 'y' => 6], ['x' => 1, 'y' => 6]]],
        ]);

        $this->actingAs($player)->postJson(route('game.reward', $tutorialSession), [
            'action' => 'extra_moves',
            'claim_token' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->postJson(route('game.reward', $tutorialSession), [
            'action' => 'double_coins',
            'claim_token' => (string) Str::uuid(),
        ])->assertForbidden();

        $laterLevel = Level::query()->where('number', 6)->firstOrFail();
        $laterSession = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $laterLevel->id,
            'status' => 'active',
            'state' => ['score' => 0, 'moves' => 0, 'bonus_moves' => 0, 'apples' => [], 'worm' => [['x' => 2, 'y' => 6], ['x' => 1, 'y' => 6]], 'extra_moves_claimed' => false],
        ]);

        $this->postJson(route('game.reward', $laterSession), [
            'action' => 'extra_moves',
            'claim_token' => (string) Str::uuid(),
        ])->assertOk()->assertJsonPath('simulated', true)->assertJsonPath('state.bonus_moves', 5);
    }

    public function test_rewarded_revive_unlocks_later_and_restores_the_last_safe_side_view_checkpoint(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create([
            'name' => 'Checkpoint Player',
            'email' => 'checkpoint-player@example.test',
            'password' => 'secret-password',
        ]);

        $levelTen = Level::query()->where('number', 10)->firstOrFail();
        $lockedRevive = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $levelTen->id,
            'status' => 'failed',
            'state' => [
                'mode' => 'side-view-puzzle', 'lost' => true, 'failure_reason' => 'The worm fell.',
                'worm' => [['x' => 8, 'y' => 6], ['x' => 7, 'y' => 6]],
                'apples' => [], 'checkpoint' => ['worm' => [['x' => 2, 'y' => 6], ['x' => 1, 'y' => 6]], 'apples' => [], 'moves' => 0, 'score' => 0],
            ],
        ]);

        $this->actingAs($player)->postJson(route('game.reward', $lockedRevive), [
            'action' => 'revive',
            'claim_token' => (string) Str::uuid(),
        ])->assertForbidden();

        $levelEleven = Level::query()->where('number', 11)->firstOrFail();
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $levelEleven->id,
            'status' => 'failed',
            'state' => [
                'mode' => 'side-view-puzzle',
                'worm' => [['x' => 9, 'y' => 6], ['x' => 8, 'y' => 6], ['x' => 7, 'y' => 6]],
                'apples' => [], 'goldenApples' => [], 'blocks' => [['x' => 9, 'y' => 7]],
                'switches' => [], 'switchesActivated' => 1, 'gatesOpen' => true,
                'moves' => 15, 'score' => 90, 'applesCollected' => 2,
                'pushes' => 3, 'gravitySteps' => 5, 'bonus_moves' => 0,
                'won' => false, 'lost' => true,
                'failure_reason' => 'Move limit reached. Restart the level and plan a more efficient route.',
                'checkpoint' => [
                    'worm' => [['x' => 4, 'y' => 6], ['x' => 3, 'y' => 6], ['x' => 2, 'y' => 6]],
                    'apples' => [['x' => 5, 'y' => 6]], 'goldenApples' => [],
                    'blocks' => [['x' => 6, 'y' => 7]], 'switches' => [['x' => 7, 'y' => 4]],
                    'switchesActivated' => 0, 'gatesOpen' => false, 'growthPending' => 0,
                    'moves' => 12, 'score' => 55, 'applesCollected' => 1,
                    'goldenApplesCollected' => 0, 'pushes' => 1, 'gravitySteps' => 2,
                ],
            ],
        ]);

        $this->postJson(route('game.reward', $session), [
            'action' => 'revive',
            'claim_token' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('state.worm.0.x', 4)
            ->assertJsonPath('state.apples.0.x', 5)
            ->assertJsonPath('state.blocks.0.x', 6)
            ->assertJsonPath('state.blocks.0.y', 7)
            ->assertJsonPath('state.gatesOpen', false)
            ->assertJsonPath('state.switchesActivated', 0)
            ->assertJsonPath('state.moves', 12)
            ->assertJsonPath('state.bonus_moves', 5)
            ->assertJsonPath('state.revive_claimed', true);

        $this->assertSame(1, (int) $session->fresh()->revives_used);
        $this->assertSame(1, RewardClaim::query()->where('game_session_id', $session->id)->where('action', 'revive')->count());
    }

    public function test_development_mock_rewards_fail_closed_when_mock_mode_is_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['game.rewarded_ads.allow_development_mock' => false]);

        $player = User::create([
            'name' => 'Production Reward Player',
            'email' => 'production-reward@example.test',
            'password' => 'secret-password',
        ]);
        $level = Level::where('number', 1)->firstOrFail();
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'score' => 20,
            'state' => ['score' => 20, 'double_score_claimed' => false],
        ]);

        $this->actingAs($player)->postJson(route('game.reward', $session), [
            'action' => 'double_score',
            'claim_token' => (string) Str::uuid(),
        ])->assertStatus(503);

        $this->assertDatabaseCount('reward_claims', 0);
        $this->assertSame(20, (int) $session->fresh()->state['score']);
    }

    public function test_double_coins_is_unavailable_after_a_level_has_been_cleared_once(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create([
            'name' => 'Replay Coin Player',
            'email' => 'replay-coins@example.test',
            'password' => 'secret-password',
        ]);
        $level = Level::where('number', 6)->firstOrFail();
        $firstSession = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => [
                'won' => true,
                'lost' => false,
                'apples' => [],
                'moves' => 1,
                'score' => 100,
            ],
        ]);

        $this->actingAs($player)
            ->postJson(route('game.complete', $firstSession))
            ->assertOk();

        $replaySession = GameSession::create([
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
                'won' => false,
                'lost' => false,
            ],
        ]);

        $this->postJson(route('game.reward', $replaySession), [
            'action' => 'double_coins',
            'claim_token' => (string) Str::uuid(),
        ])->assertStatus(422);

        $this->assertSame(180, (int) Wallet::where('user_id', $player->id)->value('coins'));
        $this->assertSame(0, RewardClaim::where('game_session_id', $replaySession->id)->count());
    }

}
