<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Level;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserLevelProgress;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_reset_their_own_progress_without_losing_coins(): void
    {
        $user = User::create([
            'name' => 'Player One',
            'email' => 'player@example.test',
            'password' => 'secret-password',
        ]);

        $otherUser = User::create([
            'name' => 'Player Two',
            'email' => 'other@example.test',
            'password' => 'secret-password',
        ]);

        $firstLevel = Level::create([
            'number' => 1, 'title' => 'First Steps', 'difficulty' => 'easy',
            'grid_rows' => 8, 'grid_cols' => 8, 'config' => [], 'is_published' => true,
        ]);
        $secondLevel = Level::create([
            'number' => 2, 'title' => 'Worm Trail', 'difficulty' => 'easy',
            'grid_rows' => 8, 'grid_cols' => 8, 'config' => [], 'is_published' => true,
        ]);

        UserLevelProgress::create([
            'user_id' => $user->id, 'level_id' => $firstLevel->id, 'unlocked' => true,
            'completed' => true, 'best_score' => 500, 'stars' => 3, 'best_moves' => 10,
        ]);
        UserLevelProgress::create([
            'user_id' => $user->id, 'level_id' => $secondLevel->id, 'unlocked' => true,
            'completed' => false, 'best_score' => 0, 'stars' => 0,
        ]);
        UserLevelProgress::create([
            'user_id' => $otherUser->id, 'level_id' => $firstLevel->id, 'unlocked' => true,
            'completed' => true, 'best_score' => 900, 'stars' => 3, 'best_moves' => 8,
        ]);

        GameSession::create([
            'user_id' => $user->id, 'level_id' => $firstLevel->id,
            'status' => 'completed', 'score' => 500, 'state' => [],
        ]);

        Wallet::create(['user_id' => $user->id, 'coins' => 660]);
        WalletTransaction::create([
            'user_id' => $user->id, 'type' => 'level_reward', 'amount' => 50,
            'balance_after' => 660, 'reference' => 'reset-test-credit',
            'description' => 'Previous level reward',
        ]);

        $this->actingAs($user)
            ->post(route('account.reset-progress'), ['password' => 'secret-password'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $this->assertDatabaseCount('game_sessions', 0);
        $this->assertDatabaseCount('user_level_progress', 2);
        $this->assertDatabaseHas('user_level_progress', [
            'user_id' => $user->id, 'level_id' => $firstLevel->id,
            'unlocked' => 1, 'completed' => 0, 'best_score' => 0, 'stars' => 0,
        ]);
        $this->assertDatabaseHas('user_level_progress', [
            'user_id' => $otherUser->id, 'level_id' => $firstLevel->id, 'completed' => 1,
        ]);
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'coins' => 660]);
        $this->assertDatabaseHas('wallet_transactions', ['reference' => 'reset-test-credit']);
    }

    public function test_reset_requires_the_current_password(): void
    {
        $user = User::create([
            'name' => 'Player', 'email' => 'player2@example.test', 'password' => 'secret-password',
        ]);
        Level::create([
            'number' => 1, 'title' => 'First Steps', 'difficulty' => 'easy',
            'grid_rows' => 8, 'grid_cols' => 8, 'config' => [], 'is_published' => true,
        ]);

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('account.reset-progress'), ['password' => 'wrong-password'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('password');
    }
}
