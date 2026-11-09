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
        $this->assertDatabaseCount('wallet_transactions', 1);
    }
}
