<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\WalletTransaction;
use App\Services\AchievementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAchievementManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create(['name' => 'Achievement Admin', 'email' => 'achievement-admin@example.test', 'password' => 'secret-password']);
        $admin->forceFill(['is_admin' => true])->save();
        return $admin;
    }

    private function seededAdmin(): User
    {
        $this->seed(DatabaseSeeder::class);
        return User::where('is_admin', true)->firstOrFail();
    }

    private function player(): User
    {
        return User::create(['name' => 'Achievement Player', 'email' => 'achievement-player@example.test', 'password' => 'secret-password']);
    }

    public function test_achievement_library_is_admin_only_and_lists_seeded_definitions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('is_admin', true)->firstOrFail();
        $player = $this->player();

        $this->get(route('admin.achievements'))->assertRedirect(route('login'));
        $this->actingAsFreshSession($player)->get(route('admin.achievements'))->assertForbidden();

        $this->actingAsFreshSession($admin)->get(route('admin.achievements'))
            ->assertOk()->assertSee('Achievement library')->assertSee('First Bite')->assertSee('Golden Hunter');
    }

    public function test_admin_can_search_and_filter_achievement_definitions(): void
    {
        $admin = $this->seededAdmin();
        Achievement::where('code', 'first_apple')->update(['is_active' => false]);

        $this->actingAsFreshSession($admin)->get(route('admin.achievements', ['q' => 'Golden', 'status' => 'active']))
            ->assertOk()->assertSee('Golden Hunter')->assertDontSee('First Bite');

        $this->get(route('admin.achievements', ['status' => 'inactive']))
            ->assertOk()->assertSee('First Bite')->assertDontSee('Golden Hunter');
    }

    public function test_admin_can_create_an_achievement_using_a_supported_metric(): void
    {
        $admin = $this->seededAdmin();

        $this->actingAsFreshSession($admin)->post(route('admin.achievements.store'), [
            'code' => 'apple_veteran',
            'name' => 'Apple Veteran',
            'description' => 'Collect three apples.',
            'icon' => '🍎',
            'metric' => 'apples',
            'target' => 3,
            'reward_coins' => 25,
        ])->assertRedirect();

        $achievement = Achievement::where('code', 'apple_veteran')->firstOrFail();
        $this->assertTrue($achievement->is_active);
        $this->assertSame(['apples' => 3, 'reward_coins' => 25], $achievement->criteria);
    }

    public function test_admin_cannot_create_duplicate_codes_or_unsupported_metrics(): void
    {
        $admin = $this->seededAdmin();
        $payload = [
            'code' => 'first_apple',
            'name' => 'Duplicate Apple',
            'description' => 'Invalid duplicate test.',
            'icon' => '🍎',
            'metric' => 'apples',
            'target' => 1,
            'reward_coins' => 0,
        ];

        $this->actingAsFreshSession($admin)->post(route('admin.achievements.store'), $payload)->assertSessionHasErrors('code');
        $payload['code'] = 'unsupported_metric';
        $payload['metric'] = 'teleports';
        $this->post(route('admin.achievements.store'), $payload)->assertSessionHasErrors('metric');
    }

    public function test_admin_can_edit_definition_without_changing_code_or_player_progress(): void
    {
        $admin = $this->seededAdmin();
        $player = $this->player();
        $achievement = Achievement::where('code', 'first_apple')->firstOrFail();
        $record = UserAchievement::create([
            'user_id' => $player->id,
            'achievement_id' => $achievement->id,
            'progress' => ['apples' => 1],
        ]);

        $this->actingAsFreshSession($admin)->put(route('admin.achievements.update', $achievement), [
            'name' => 'First Orchard Bite',
            'description' => 'Collect two apples on your adventure.',
            'icon' => '🍏',
            'metric' => 'apples',
            'target' => 2,
            'reward_coins' => 12,
        ])->assertRedirect(route('admin.achievements.show', $achievement));

        $this->assertSame('first_apple', $achievement->fresh()->code);
        $this->assertSame('First Orchard Bite', $achievement->fresh()->name);
        $this->assertSame(['apples' => 1], $record->fresh()->progress);
    }

    public function test_metric_cannot_change_after_player_progress_has_been_recorded(): void
    {
        $admin = $this->seededAdmin();
        $player = $this->player();
        $achievement = Achievement::where('code', 'first_apple')->firstOrFail();
        UserAchievement::create(['user_id' => $player->id, 'achievement_id' => $achievement->id, 'progress' => ['apples' => 1]]);

        $this->actingAsFreshSession($admin)->put(route('admin.achievements.update', $achievement), [
            'name' => $achievement->name,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'metric' => 'levels',
            'target' => 2,
            'reward_coins' => 10,
        ])->assertSessionHasErrors();

        $this->assertArrayHasKey('apples', $achievement->fresh()->criteria);
    }

    public function test_deactivating_achievement_preserves_history_and_stops_new_progress(): void
    {
        $admin = $this->seededAdmin();
        $player = $this->player();
        $achievement = Achievement::where('code', 'first_apple')->firstOrFail();
        $record = UserAchievement::create([
            'user_id' => $player->id,
            'achievement_id' => $achievement->id,
            'progress' => ['apples' => 1],
            'unlocked_at' => now(),
        ]);
        WalletTransaction::create([
            'user_id' => $player->id,
            'type' => 'achievement_reward',
            'amount' => 10,
            'balance_after' => 110,
            'reference' => 'achievement-' . $player->id . '-' . $achievement->id,
            'description' => 'Achievement unlocked: First Bite',
        ]);

        $this->actingAsFreshSession($admin)->patch(route('admin.achievements.status', $achievement))
            ->assertRedirect(route('admin.achievements.show', $achievement));

        app(AchievementService::class)->recordCounter($player, 'apples');
        $this->assertFalse($achievement->fresh()->is_active);
        $this->assertNotNull($record->fresh()->unlocked_at);
        $this->assertSame(1, UserAchievement::whereKey($record->id)->count());
        $this->assertSame(1, WalletTransaction::where('reference', 'achievement-' . $player->id . '-' . $achievement->id)->count());
    }

    public function test_inactive_achievement_remains_visible_to_players_who_already_unlocked_it(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = $this->player();
        $achievement = Achievement::where('code', 'first_apple')->firstOrFail();
        $achievement->update(['is_active' => false]);
        UserAchievement::create([
            'user_id' => $player->id,
            'achievement_id' => $achievement->id,
            'progress' => ['apples' => 1],
            'unlocked_at' => now(),
        ]);

        $this->actingAsFreshSession($player)->get(route('dashboard'))->assertOk()->assertSee('First Bite');
    }
}
