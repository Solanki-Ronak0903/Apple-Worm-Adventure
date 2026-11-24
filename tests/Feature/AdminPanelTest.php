<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\GameSession;
use App\Models\Level;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserLevelProgress;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(string $email = 'admin-panel@example.test'): User
    {
        $admin = User::create(['name' => 'Panel Administrator', 'email' => $email, 'password' => 'secret-password']);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function createPlayer(string $name = 'Puzzle Player', string $email = 'puzzle-player@example.test'): User
    {
        return User::create(['name' => $name, 'email' => $email, 'password' => 'secret-password']);
    }

    public function test_admin_dashboard_is_restricted_and_renders_operational_metrics(): void
    {
        $admin = $this->createAdmin();
        $player = $this->createPlayer();
        $level = Level::create([
            'number' => 1,
            'title' => 'First Bite',
            'difficulty' => 'tutorial',
            'grid_rows' => 8,
            'grid_cols' => 14,
            'config' => ['sideView' => ['world' => ['name' => 'Sunlit Meadow']]],
            'is_published' => true,
        ]);
        Wallet::create(['user_id' => $player->id, 'coins' => 125]);
        GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'completed',
            'score' => 150,
            'moves_used' => 12,
            'state' => ['won' => true],
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAsFreshSession($player)->get(route('admin.dashboard'))->assertForbidden();

        $this->actingAsFreshSession($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Registered players')
            ->assertSee('Active players')
            ->assertSee('Weekly game activity')
            ->assertSee('First Bite')
            ->assertSee('Puzzle Player');
    }

    public function test_admin_can_search_filter_and_sort_player_directory(): void
    {
        $admin = $this->createAdmin();
        $this->createPlayer('Mira Puzzle', 'mira@example.test');
        $this->createPlayer('Arun Player', 'arun@example.test');

        $this->actingAsFreshSession($admin)
            ->get(route('admin.players', ['q' => 'mira', 'role' => 'player', 'sort' => 'newest']))
            ->assertOk()
            ->assertSee('Mira Puzzle')
            ->assertDontSee('Arun Player')
            ->assertSee('Player')
            ->assertSee('directory.');
    }

    public function test_admin_can_inspect_player_progress_wallet_sessions_and_achievements(): void
    {
        $admin = $this->createAdmin();
        $player = $this->createPlayer();
        $level = Level::create([
            'number' => 1,
            'title' => 'First Bite',
            'difficulty' => 'tutorial',
            'grid_rows' => 8,
            'grid_cols' => 14,
            'config' => [],
            'is_published' => true,
        ]);
        Wallet::create(['user_id' => $player->id, 'coins' => 90]);
        UserLevelProgress::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'unlocked' => true,
            'completed' => true,
            'best_score' => 250,
            'stars' => 3,
            'best_moves' => 10,
            'completed_at' => now(),
        ]);
        GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'completed',
            'score' => 250,
            'moves_used' => 10,
            'state' => ['won' => true],
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
        ]);
        WalletTransaction::create([
            'user_id' => $player->id,
            'type' => 'level_reward',
            'amount' => 10,
            'balance_after' => 90,
            'reference' => 'admin-test-ledger-1',
            'description' => 'Level completion reward',
        ]);
        $achievement = Achievement::create([
            'code' => 'admin_test_first',
            'name' => 'First Steps',
            'description' => 'Complete a first level.',
            'icon' => '⭐',
            'criteria' => ['levels' => 1],
        ]);
        UserAchievement::create([
            'user_id' => $player->id,
            'achievement_id' => $achievement->id,
            'progress' => ['levels' => 1],
            'unlocked_at' => now(),
        ]);

        $this->actingAsFreshSession($admin)
            ->get(route('admin.players.show', $player))
            ->assertOk()
            ->assertSee('Puzzle Player')
            ->assertSee('90')
            ->assertSee('First Bite')
            ->assertSee('First Steps')
            ->assertSee('Level completion reward');

        $this->actingAsFreshSession($player)
            ->get(route('admin.players.show', $player))
            ->assertForbidden();
    }
}
