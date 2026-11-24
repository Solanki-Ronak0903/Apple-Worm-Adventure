<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\AdminAuditLog;
use App\Models\AdminSetting;
use App\Models\GameSession;
use App\Models\Level;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Settings Administrator',
            'email' => 'settings-admin@example.test',
            'password' => 'secret-password',
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function player(): User
    {
        return User::create([
            'name' => 'Settings Player',
            'email' => 'settings-player@example.test',
            'password' => 'secret-password',
        ]);
    }

    private function level(): Level
    {
        return Level::create([
            'number' => 1,
            'title' => 'Settings Test Level',
            'difficulty' => 'tutorial',
            'grid_rows' => 8,
            'grid_cols' => 14,
            'config' => ['sideView' => ['world' => ['code' => 'meadow', 'name' => 'Sunlit Meadow']]],
            'is_published' => true,
        ]);
    }

    public function test_settings_and_audit_pages_are_restricted_to_administrators(): void
    {
        $admin = $this->admin();
        $player = $this->player();

        $this->get(route('admin.settings'))->assertRedirect(route('login'));
        $this->get(route('admin.audit'))->assertRedirect(route('login'));

        $this->actingAsFreshSession($player)->get(route('admin.settings'))->assertForbidden();
        $this->actingAsFreshSession($player)->get(route('admin.audit'))->assertForbidden();

        $this->actingAsFreshSession($admin)->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Application settings')
            ->assertSee('Allow new registrations')
            ->assertSee('Require verified email before playing')
            ->assertSee('Starting coins for new players');

        $this->get(route('admin.audit'))
            ->assertOk()
            ->assertSee('Audit history');
    }

    public function test_admin_can_update_settings_and_audit_before_and_after_values(): void
    {
        $admin = $this->admin();

        $this->actingAsFreshSession($admin)->put(route('admin.settings.update'), [
            'allow_registration' => '1',
            'require_email_verification' => '1',
            'leaderboard_enabled' => '0',
            'rewarded_actions_enabled' => '1',
            'new_player_starting_coins' => 250,
        ])->assertRedirect(route('admin.settings'))->assertSessionHas('status');

        $this->assertTrue(AdminSetting::getValue('allow_registration'));
        $this->assertTrue(AdminSetting::getValue('require_email_verification'));
        $this->assertFalse(AdminSetting::getValue('leaderboard_enabled'));
        $this->assertTrue(AdminSetting::getValue('rewarded_actions_enabled'));
        $this->assertSame(250, AdminSetting::getValue('new_player_starting_coins'));

        $log = AdminAuditLog::query()->where('action', 'settings.updated')->firstOrFail();
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame(false, $log->after['leaderboard_enabled']);
        $this->assertSame(100, $log->before['new_player_starting_coins']);
        $this->assertSame(250, $log->after['new_player_starting_coins']);
        $this->assertStringNotContainsString('password', json_encode($log->after));
    }

    public function test_settings_are_enforced_by_registration_leaderboard_and_reward_routes(): void
    {
        $admin = $this->admin();
        $player = $this->player();
        $level = $this->level();
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => ['mode' => 'side-view-puzzle'],
        ]);

        $this->actingAsFreshSession($admin)->put(route('admin.settings.update'), [
            'allow_registration' => '0',
            'leaderboard_enabled' => '0',
            'rewarded_actions_enabled' => '0',
            'new_player_starting_coins' => 100,
        ])->assertRedirect(route('admin.settings'));

        auth()->logout();
        $this->get(route('register'))->assertForbidden();
        $this->post(route('register.store'), [
            'name' => 'Blocked Signup',
            'email' => 'blocked-signup@example.test',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertForbidden();

        $this->actingAsFreshSession($player)->get(route('leaderboard'))->assertNotFound();
        $this->post(route('game.reward', $session), [
            'action' => 'revive',
            'claim_token' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertForbidden();
    }

    public function test_new_player_starting_coins_only_affects_new_accounts(): void
    {
        $admin = $this->admin();
        $existingPlayer = $this->player();
        $existingWallet = Wallet::create(['user_id' => $existingPlayer->id, 'coins' => 725]);

        $this->actingAsFreshSession($admin)->put(route('admin.settings.update'), [
            'allow_registration' => '1',
            'leaderboard_enabled' => '1',
            'rewarded_actions_enabled' => '1',
            'new_player_starting_coins' => 350,
        ])->assertRedirect(route('admin.settings'));

        auth()->logout();
        $this->post(route('register.store'), [
            'name' => 'New Signup',
            'email' => 'new-signup@example.test',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect(route('dashboard'));

        $newPlayer = User::where('email', 'new-signup@example.test')->firstOrFail();
        $this->assertSame(350, $newPlayer->wallet()->value('coins'));
        $this->assertSame(725, $existingWallet->fresh()->coins);
    }

    public function test_audit_log_filters_and_tracks_level_and_achievement_changes(): void
    {
        $admin = $this->admin();
        $level = $this->level();
        $achievement = Achievement::create([
            'code' => 'audit_first_steps',
            'name' => 'First Steps',
            'description' => 'Complete one level.',
            'icon' => '⭐',
            'criteria' => ['levels' => 1, 'reward_coins' => 5],
            'is_active' => true,
        ]);

        $this->actingAsFreshSession($admin)->put(route('admin.levels.update', $level), [
            'title' => 'Audited Level Title',
            'difficulty' => 'easy',
            'lesson' => 'A safe lesson for the first puzzle.',
            'hint_1' => 'Look at the apple.',
            'hint_2' => 'Move across the open tiles.',
            'hint_3' => 'Reach the portal after collecting the apple.',
        ])->assertRedirect();

        $this->put(route('admin.achievements.update', $achievement), [
            'name' => 'First Steps Plus',
            'description' => 'Complete your first published level.',
            'icon' => '🏅',
            'metric' => 'levels',
            'target' => 1,
            'reward_coins' => 10,
        ])->assertRedirect();

        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'level.metadata_updated',
            'subject_type' => 'Level',
            'subject_id' => $level->id,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'achievement.updated',
            'subject_type' => 'Achievement',
            'subject_id' => $achievement->id,
        ]);

        $this->get(route('admin.audit', ['action' => 'level.metadata_updated']))
            ->assertOk()
            ->assertSee('Audited Level Title')
            ->assertDontSee('First Steps Plus');
    }
}
