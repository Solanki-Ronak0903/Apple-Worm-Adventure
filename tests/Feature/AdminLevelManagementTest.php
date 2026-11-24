<?php

namespace Tests\Feature;

use App\Game\SideViewLevelCatalog;
use App\Models\GameSession;
use App\Models\Level;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLevelManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Level Administrator',
            'email' => 'level-admin@example.test',
            'password' => 'secret-password',
        ]);
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function seededAdmin(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::where('is_admin', true)->firstOrFail();
    }

    public function test_level_library_is_admin_only_and_lists_campaign_levels(): void
    {
        $admin = $this->seededAdmin();
        $player = User::create(['name' => 'Regular Player', 'email' => 'regular-level@example.test', 'password' => 'secret-password']);

        $this->get(route('admin.levels'))->assertRedirect(route('login'));
        $this->actingAsFreshSession($player)->get(route('admin.levels'))->assertForbidden();

        $this->actingAsFreshSession($admin)
            ->get(route('admin.levels'))
            ->assertOk()
            ->assertSee('Level library')
            ->assertSee('First Bite')
            ->assertSee('Showing 1–15 of 25 levels');

        // Sky Temple is on the second group of campaign levels. Use the world
        // filter instead of assuming it appears on the first paginated page.
        $this->get(route('admin.levels', ['world' => 'cloud']))
            ->assertOk()
            ->assertSee('Sky Temple');
    }

    public function test_admin_can_filter_levels_by_world_difficulty_and_status(): void
    {
        $admin = $this->seededAdmin();

        $this->actingAsFreshSession($admin)
            ->get(route('admin.levels', ['world' => 'fernwood', 'difficulty' => 'easy', 'status' => 'published']))
            ->assertOk()
            ->assertSee('Fernwood Trail')
            ->assertSee('High Orchard')
            ->assertDontSee('First Bite');
    }

    public function test_level_detail_shows_read_only_map_preview_and_metadata_form(): void
    {
        $admin = $this->seededAdmin();
        $level = Level::where('number', 1)->firstOrFail();

        $this->actingAsFreshSession($admin)
            ->get(route('admin.levels.show', $level))
            ->assertOk()
            ->assertSee('Puzzle map preview')
            ->assertSee('First Bite')
            ->assertSee('Progressive hint 1')
            ->assertSee('protected in this release');
    }

    public function test_admin_can_update_safe_metadata_without_changing_puzzle_geometry(): void
    {
        $admin = $this->seededAdmin();
        $level = Level::where('number', 1)->firstOrFail();
        $originalLayout = $level->config['sideView'];

        $this->actingAsFreshSession($admin)
            ->put(route('admin.levels.update', $level), [
                'title' => 'First Orchard',
                'difficulty' => 'easy',
                'lesson' => 'Collect the fruit and follow the safe ground to the portal.',
                'hint_1' => 'Begin on the lower path.',
                'hint_2' => 'Move toward the red apple before turning.',
                'hint_3' => 'Collect the apple, then continue to the portal.',
            ])
            ->assertRedirect(route('admin.levels.show', $level))
            ->assertSessionHas('status');

        $updated = $level->fresh();
        $this->assertSame('First Orchard', $updated->title);
        $this->assertSame('easy', $updated->difficulty);
        $this->assertSame('Collect the fruit and follow the safe ground to the portal.', $updated->config['sideView']['lesson']);
        $this->assertSame(['Begin on the lower path.', 'Move toward the red apple before turning.', 'Collect the apple, then continue to the portal.'], $updated->config['sideView']['hintStages']);
        $this->assertSame($originalLayout['terrain'], $updated->config['sideView']['terrain']);
        $this->assertSame($originalLayout['apples'], $updated->config['sideView']['apples']);
        $this->assertSame($originalLayout['portal'], $updated->config['sideView']['portal']);
    }

    public function test_admin_can_validate_a_level_without_creating_player_history(): void
    {
        $admin = $this->seededAdmin();
        $level = Level::where('number', 1)->firstOrFail();
        $sessionsBefore = GameSession::count();

        $this->actingAsFreshSession($admin)
            ->post(route('admin.levels.validate', $level))
            ->assertRedirect(route('admin.levels.show', $level))
            ->assertSessionHas('status');

        $this->assertSame($sessionsBefore, GameSession::count(), 'Temporary validation sessions must be deleted.');
    }

    public function test_admin_can_unpublish_and_republish_the_last_level_after_server_replay(): void
    {
        $admin = $this->seededAdmin();
        $level = Level::where('number', 25)->firstOrFail();

        $this->actingAsFreshSession($admin)
            ->patch(route('admin.levels.publication', $level))
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertFalse($level->fresh()->is_published);

        $this->patch(route('admin.levels.publication', $level))
            ->assertRedirect()
            ->assertSessionHas('status');
        $this->assertTrue($level->fresh()->is_published);
    }

    public function test_admin_cannot_publish_a_level_with_invalid_geometry(): void
    {
        $admin = $this->seededAdmin();
        $level = Level::where('number', 25)->firstOrFail();
        $config = $level->config;
        $config['sideView']['portal'] = ['x' => 99, 'y' => 99];
        $level->update(['config' => $config, 'is_published' => false]);

        $this->actingAsFreshSession($admin)
            ->patch(route('admin.levels.publication', $level))
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertFalse($level->fresh()->is_published);
    }

    public function test_published_levels_must_remain_sequential_and_active_sessions_block_unpublishing(): void
    {
        $admin = $this->seededAdmin();
        $level24 = Level::where('number', 24)->firstOrFail();
        $level25 = Level::where('number', 25)->firstOrFail();

        $this->actingAsFreshSession($admin)
            ->patch(route('admin.levels.publication', $level24))
            ->assertSessionHasErrors();
        $this->assertTrue($level24->fresh()->is_published);

        GameSession::create([
            'user_id' => $admin->id,
            'level_id' => $level25->id,
            'status' => 'active',
            'state' => ['mode' => 'side-view-puzzle'],
        ]);

        $this->patch(route('admin.levels.publication', $level25))
            ->assertSessionHasErrors();
        $this->assertTrue($level25->fresh()->is_published);
    }

    public function test_database_seeder_preserves_admin_level_edits_and_publication_status(): void
    {
        $this->seed(DatabaseSeeder::class);
        $level = Level::where('number', 25)->firstOrFail();
        $config = $level->config;
        $config['sideView']['lesson'] = 'Admin-authored lesson remains intact.';
        $level->update([
            'title' => 'Admin Final Trial',
            'difficulty' => 'hard',
            'config' => $config,
            'is_published' => false,
        ]);

        $this->seed(DatabaseSeeder::class);

        $reseeded = $level->fresh();
        $this->assertSame('Admin Final Trial', $reseeded->title);
        $this->assertSame('hard', $reseeded->difficulty);
        $this->assertSame('Admin-authored lesson remains intact.', $reseeded->config['sideView']['lesson']);
        $this->assertFalse($reseeded->is_published);
    }
}
