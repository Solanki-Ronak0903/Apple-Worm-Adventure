<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Level;
use App\Models\RewardClaim;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Analytics Administrator',
            'email' => 'analytics-admin@example.test',
            'password' => 'secret-password',
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function player(string $email = 'analytics-player@example.test'): User
    {
        return User::create([
            'name' => 'Analytics Player',
            'email' => $email,
            'password' => 'secret-password',
        ]);
    }

    private function level(int $number = 1, string $title = 'Analytics Meadow'): Level
    {
        return Level::create([
            'number' => $number,
            'title' => $title,
            'difficulty' => 'easy',
            'grid_rows' => 8,
            'grid_cols' => 14,
            'config' => [
                'sideView' => [
                    'world' => ['code' => 'meadow', 'name' => 'Sunlit Meadow'],
                ],
            ],
            'is_published' => true,
        ]);
    }

    private function createGameSession(User $user, Level $level, string $status, int $moves = 0, int $daysAgo = 0): GameSession
    {
        $session = GameSession::create([
            'user_id' => $user->id,
            'level_id' => $level->id,
            'status' => $status,
            'score' => $status === 'completed' ? 100 : 0,
            'moves_used' => $moves,
            'apples_collected' => 1,
            'hints_used' => 1,
            'revives_used' => 0,
            'boosters_used' => 0,
            'state' => ['won' => $status === 'completed'],
            'started_at' => now()->subMinutes(10),
            'finished_at' => $status === 'active' ? null : now(),
        ]);

        if ($daysAgo > 0) {
            $session->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        }

        return $session;
    }

    public function test_game_analytics_is_restricted_to_administrators(): void
    {
        $admin = $this->admin();
        $player = $this->player();

        $this->get(route('admin.analytics'))->assertRedirect(route('login'));
        $this->actingAsFreshSession($player)->get(route('admin.analytics'))->assertForbidden();
        $this->actingAsFreshSession($admin)->get(route('admin.analytics'))
            ->assertOk()
            ->assertSee('Game analytics')
            ->assertSee('Session activity')
            ->assertSee('Level performance detail')
            ->assertSee('Wallet movement');
    }

    public function test_analytics_aggregates_player_sessions_and_excludes_admin_activity(): void
    {
        $admin = $this->admin();
        $playerOne = $this->player();
        $playerTwo = $this->player('analytics-player-two@example.test');
        $level = $this->level();

        $this->createGameSession($playerOne, $level, 'completed', 12);
        $this->createGameSession($playerOne, $level, 'failed', 8);
        $this->createGameSession($playerTwo, $level, 'active');
        $this->createGameSession($playerOne, $level, 'completed', 7, 45);
        $this->createGameSession($admin, $level, 'completed', 2);

        $response = $this->actingAsFreshSession($admin)->get(route('admin.analytics', ['period' => 30]));

        $response->assertOk()->assertViewHas('metrics', function (array $metrics): bool {
            return $metrics['sessions'] === 3
                && $metrics['completed'] === 1
                && $metrics['failed'] === 1
                && $metrics['active_sessions'] === 1
                && $metrics['active_players'] === 2
                && $metrics['returning_players'] === 1
                && $metrics['average_moves'] === 12.0
                && $metrics['completion_rate'] === 33.3;
        });

        $response->assertViewHas('levelPerformance', function ($levels) use ($level): bool {
            $row = $levels->firstWhere('id', $level->id);

            return $row !== null
                && $row['sessions'] === 3
                && $row['completed'] === 1
                && $row['failed'] === 1
                && $row['world'] === 'Sunlit Meadow';
        });
    }

    public function test_analytics_period_filter_excludes_sessions_older_than_selected_window(): void
    {
        $admin = $this->admin();
        $player = $this->player();
        $level = $this->level();

        $this->createGameSession($player, $level, 'completed', 9);
        $this->createGameSession($player, $level, 'failed', 6, 12);

        $this->actingAsFreshSession($admin)
            ->get(route('admin.analytics', ['period' => 7]))
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics) => $metrics['sessions'] === 1 && $metrics['completed'] === 1);

        $this->get(route('admin.analytics', ['period' => 30]))
            ->assertOk()
            ->assertViewHas('metrics', fn (array $metrics) => $metrics['sessions'] === 2 && $metrics['failed'] === 1);

        $this->get(route('admin.analytics', ['period' => 14]))->assertSessionHasErrors('period');
    }

    public function test_analytics_reports_reward_claims_and_signed_wallet_ledger_totals(): void
    {
        $admin = $this->admin();
        $player = $this->player();

        RewardClaim::create([
            'user_id' => $player->id,
            'provider' => 'development_mock',
            'action' => 'double_coins',
            'claim_token' => (string) Str::uuid(),
            'status' => 'granted',
            'granted_at' => now(),
        ]);
        RewardClaim::create([
            'user_id' => $player->id,
            'provider' => 'development_mock',
            'action' => 'revive',
            'claim_token' => (string) Str::uuid(),
            'status' => 'pending',
        ]);

        WalletTransaction::create([
            'user_id' => $player->id,
            'type' => 'level_reward',
            'amount' => 30,
            'balance_after' => 130,
            'reference' => 'analytics-credit-' . $player->id,
            'description' => 'Analytics test credit',
        ]);
        WalletTransaction::create([
            'user_id' => $player->id,
            'type' => 'power_up_purchase',
            'amount' => -10,
            'balance_after' => 120,
            'reference' => 'analytics-debit-' . $player->id,
            'description' => 'Analytics test debit',
        ]);

        $this->actingAsFreshSession($admin)
            ->get(route('admin.analytics'))
            ->assertOk()
            ->assertViewHas('metrics', function (array $metrics): bool {
                return $metrics['granted_claims'] === 1
                    && $metrics['pending_claims'] === 1
                    && $metrics['coins_credited'] === 30
                    && $metrics['coins_debited'] === 10;
            })
            ->assertSee('Double coins')
            ->assertSee('Revive')
            ->assertSee('Level reward')
            ->assertSee('Power up purchase');
    }
}
