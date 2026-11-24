<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Level;
use App\Models\User;
use App\Models\UserLevelProgress;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialReleaseLevelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_release_migration_hides_existing_future_levels_and_seeder_preserves_later_admin_choice(): void
    {
        $futureLevel = Level::create([
            'number' => 26,
            'title' => 'Future Level 26',
            'difficulty' => 'expert',
            'grid_rows' => 12,
            'grid_cols' => 12,
            'config' => [],
            'is_published' => true,
        ]);

        // In production this migration runs once before the initial seeder.
        // RefreshDatabase has already run the migrations, so invoke this
        // migration against the pre-existing future row to verify that policy.
        $initialReleaseMigration = require database_path('migrations/2026_11_02_000001_limit_initial_release_to_25_levels.php');
        $initialReleaseMigration->up();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(25, Level::where('is_published', true)->count());
        $this->assertDatabaseHas('levels', ['number' => 26, 'is_published' => 0]);

        // The migration updates the row through the query builder, so the
        // previously-created Eloquent instance still holds its old in-memory
        // value. Refresh it before simulating an administrator publishing it.
        $futureLevel->refresh();
        $this->assertFalse($futureLevel->is_published);

        // Once an administrator explicitly publishes a future level, running
        // the seeder again must not silently reverse that decision.
        $futureLevel->update(['is_published' => true]);
        $this->assertTrue($futureLevel->fresh()->is_published);

        $this->seed(DatabaseSeeder::class);
        $this->assertTrue($futureLevel->fresh()->is_published);

        // Only the original 25 campaign maps are subject to this release's
        // planned-route assertions. Level 26 is a future placeholder in this
        // test and intentionally has no approved puzzle configuration yet.
        foreach (Level::where('is_published', true)
            ->whereBetween('number', [1, 25])
            ->orderBy('number')
            ->get() as $level) {
            $config = $level->config;
            $this->assertArrayHasKey('plannedRoute', $config);
            $this->assertArrayNotHasKey('goldenApple', $config);

            $routeKeys = array_map(
                fn (array $point) => $point['x'] . ':' . $point['y'],
                $config['plannedRoute']
            );

            $this->assertSame($config['wormStart'], $config['plannedRoute'][0]);
            $this->assertSame($config['portal'], $config['plannedRoute'][count($config['plannedRoute']) - 1]);
            $this->assertSame(count($routeKeys), count(array_unique($routeKeys)));

            foreach ($config['apples'] as $apple) {
                $this->assertContains($apple['x'] . ':' . $apple['y'], $routeKeys);
            }

            foreach ($config['obstacles'] as $obstacle) {
                $this->assertNotContains($obstacle['x'] . ':' . $obstacle['y'], $routeKeys);
            }

            foreach (($config['goldenApples'] ?? []) as $goldenApple) {
                $goldenKey = $goldenApple['x'] . ':' . $goldenApple['y'];
                $this->assertContains($goldenKey, $routeKeys);
                $this->assertNotContains($goldenKey, array_map(fn ($point) => $point['x'] . ':' . $point['y'], $config['apples']));
                $this->assertNotContains($goldenKey, array_map(fn ($point) => $point['x'] . ':' . $point['y'], $config['obstacles']));
            }
        }
    }

    public function test_unpublished_level_26_cannot_be_started(): void
    {
        $this->seed(DatabaseSeeder::class);

        $level26 = Level::create([
            'number' => 26,
            'title' => 'Future Level 26',
            'difficulty' => 'expert',
            'grid_rows' => 12,
            'grid_cols' => 12,
            'config' => [],
            'is_published' => false,
        ]);

        $player = User::create([
            'name' => 'Test Player',
            'email' => 'release-player@example.test',
            'password' => 'secret-password',
        ]);

        $this->actingAs($player)
            ->get('/play/' . $level26->number)
            ->assertNotFound();
    }

    public function test_completing_level_25_does_not_unlock_unpublished_level_26(): void
    {
        $this->seed(DatabaseSeeder::class);

        $player = User::create([
            'name' => 'Final Level Player',
            'email' => 'final-level@example.test',
            'password' => 'secret-password',
        ]);

        $level25 = Level::where('number', 25)->firstOrFail();
        $level26 = Level::create([
            'number' => 26,
            'title' => 'Future Level 26',
            'difficulty' => 'expert',
            'grid_rows' => 12,
            'grid_cols' => 12,
            'config' => [],
            'is_published' => false,
        ]);

        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level25->id,
            'status' => 'active',
            'score' => 100,
            'moves_used' => 1,
            'apples_collected' => count($level25->config['apples']),
            'state' => [
                'won' => true,
                'lost' => false,
                'worm' => array_reverse(array_slice($level25->config['plannedRoute'], -7)),
                'apples' => [],
                'goldenApples' => [],
                'megaApples' => [],
                'mysteryApples' => [],
                'keys' => [],
                'switches' => [],
                'growthPending' => 0,
                'moves' => 1,
                'score' => 100,
            ],
        ]);

        $this->actingAs($player)
            ->postJson(route('game.complete', $session))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('user_level_progress', [
            'user_id' => $player->id,
            'level_id' => $level25->id,
            'completed' => 1,
        ]);

        $this->assertDatabaseMissing('user_level_progress', [
            'user_id' => $player->id,
            'level_id' => $level26->id,
        ]);
    }

    public function test_leaderboard_ignores_unpublished_future_level_scores(): void
    {
        $this->seed(DatabaseSeeder::class);

        $player = User::create([
            'name' => 'Visible Player',
            'email' => 'visible-player@example.test',
            'password' => 'secret-password',
        ]);

        $level1 = Level::where('number', 1)->firstOrFail();
        $level26 = Level::create([
            'number' => 26,
            'title' => 'Future Level 26',
            'difficulty' => 'expert',
            'grid_rows' => 12,
            'grid_cols' => 12,
            'config' => [],
            'is_published' => false,
        ]);

        UserLevelProgress::create([
            'user_id' => $player->id,
            'level_id' => $level1->id,
            'unlocked' => true,
            'completed' => true,
            'best_score' => 125,
            'stars' => 2,
        ]);

        UserLevelProgress::create([
            'user_id' => $player->id,
            'level_id' => $level26->id,
            'unlocked' => true,
            'completed' => true,
            'best_score' => 9999,
            'stars' => 3,
        ]);

        $this->actingAs($player)
            ->get(route('leaderboard'))
            ->assertOk()
            ->assertSee('125')
            ->assertDontSee('9,999');
    }
}
