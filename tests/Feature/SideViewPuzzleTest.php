<?php

namespace Tests\Feature;

use App\Game\SideViewLevelSolutions;
use App\Models\GameSession;
use App\Models\Level;
use App\Models\User;
use App\Models\UserLevelProgress;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SideViewPuzzleTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $email = 'sideview-player@example.test'): User
    {
        return User::create(['name' => 'Puzzle Player', 'email' => $email, 'password' => 'secret-password']);
    }

    public function test_all_25_levels_have_hand_authored_side_view_layouts_and_distinct_map_signatures(): void
    {
        $this->seed(DatabaseSeeder::class);
        $levels = Level::query()->where('is_published', true)->orderBy('number')->get();

        $this->assertCount(25, $levels);
        $signatures = [];
        foreach ($levels as $level) {
            $layout = $level->config['sideView'] ?? null;
            $this->assertIsArray($layout);
            $this->assertSame(14, $layout['width']);
            $this->assertSame(8, $layout['height']);
            $this->assertNotEmpty($layout['lesson']);
            $this->assertNotEmpty($layout['terrain']);
            $signatures[] = sha1(json_encode([
                $layout['terrain'], $layout['apples'], $layout['goldenApples'] ?? [], $layout['portal'], $layout['blocks'], $layout['spikes'], $layout['switches'] ?? [], $layout['gates'] ?? [], $layout['start'],
            ]));

            $terrain = collect($layout['terrain'])->map(fn ($point) => $point['x'] . ':' . $point['y'])->all();
            $spikes = collect($layout['spikes'])->map(fn ($point) => $point['x'] . ':' . $point['y'])->all();
            $blocks = collect($layout['blocks'])->map(fn ($point) => $point['x'] . ':' . $point['y'])->all();
            [$bodyDx, $bodyDy] = match ($layout['start']['facing'] ?? 'right') {
                'left' => [1, 0], 'up' => [0, 1], 'down' => [0, -1], default => [-1, 0],
            };
            $startCells = collect(range(0, $layout['start']['length'] - 1))
                ->map(fn ($offset) => ($layout['start']['x'] + ($bodyDx * $offset)) . ':' . ($layout['start']['y'] + ($bodyDy * $offset)));
            $allPoints = array_merge(
                $layout['terrain'], $layout['apples'], $layout['blocks'], $layout['spikes'],
                $layout['switches'] ?? [], $layout['gates'] ?? [], [$layout['portal']],
                $startCells->map(function ($cell) {
                    [$x, $y] = array_map('intval', explode(':', $cell));
                    return ['x' => $x, 'y' => $y];
                })->all()
            );
            foreach ($allPoints as $point) {
                $this->assertGreaterThanOrEqual(0, $point['x']);
                $this->assertLessThan($layout['width'], $point['x']);
                $this->assertGreaterThanOrEqual(0, $point['y']);
                $this->assertLessThan($layout['height'], $point['y']);
            }

            foreach ($startCells as $cell) {
                $this->assertNotContains($cell, $terrain);
                $this->assertNotContains($cell, $spikes);
                $this->assertNotContains($cell, $blocks);
            }
            foreach ($layout['blocks'] as $block) {
                $cell = $block['x'] . ':' . $block['y'];
                $this->assertNotContains($cell, $terrain);
                $this->assertNotContains($cell, $spikes);
            }
            foreach ($layout['apples'] as $apple) {
                $cell = $apple['x'] . ':' . $apple['y'];
                $this->assertNotContains($cell, $terrain);
                $this->assertNotContains($cell, $spikes);
                $this->assertNotContains($cell, $blocks);
            }
            foreach (array_merge($layout['goldenApples'] ?? [], $layout['switches'] ?? [], $layout['gates'] ?? []) as $mechanism) {
                $cell = $mechanism['x'] . ':' . $mechanism['y'];
                $this->assertNotContains($cell, $terrain);
                $this->assertNotContains($cell, $spikes);
                $this->assertNotContains($cell, $blocks);
            }
            $portalCell = $layout['portal']['x'] . ':' . $layout['portal']['y'];
            $this->assertNotContains($portalCell, $terrain);
            $this->assertNotContains($portalCell, $spikes);
            $this->assertNotContains($portalCell, $blocks);
        }

        $this->assertSame(count($signatures), count(array_unique($signatures)), 'Every level should have its own authored board layout.');
    }

    public function test_opening_five_levels_teach_the_core_puzzle_mechanics_in_order(): void
    {
        $this->seed(DatabaseSeeder::class);
        $layouts = Level::query()
            ->whereIn('number', [1, 2, 3, 4, 5])
            ->orderBy('number')
            ->get()
            ->mapWithKeys(fn (Level $level) => [$level->number => $level->config['sideView']]);

        $this->assertCount(5, $layouts);

        // Level 1: a first body-position puzzle, with no hazards or crates.
        $this->assertCount(1, $layouts[1]['apples']);
        $this->assertEmpty($layouts[1]['blocks']);
        $this->assertEmpty($layouts[1]['spikes']);
        $this->assertEmpty($layouts[1]['switches']);

        // Level 2: the lower floor is broken across four cells; the upper shelf
        // is the only safe connection between the two banks.
        $levelTwoTerrain = collect($layouts[2]['terrain'])->map(fn ($point) => $point['x'] . ':' . $point['y'])->all();
        foreach ([5, 6, 7, 8] as $gapX) {
            $this->assertNotContains($gapX . ':7', $levelTwoTerrain);
        }
        $this->assertSame([['x' => 3, 'y' => 6], ['x' => 8, 'y' => 3]], $layouts[2]['apples']);

        // Level 3: staggered shelves and a tall central divider require a
        // different-height crossing and two separate apple approaches.
        $levelThreeTerrain = collect($layouts[3]['terrain'])->map(fn ($point) => $point['x'] . ':' . $point['y'])->all();
        $this->assertContains('6:3', $levelThreeTerrain);
        $this->assertContains('7:4', $levelThreeTerrain);
        $this->assertSame([['x' => 4, 'y' => 4], ['x' => 8, 'y' => 3]], $layouts[3]['apples']);

        // Level 4: the obvious floor route is trapped by a thorn strip between
        // two stone fins; the safe route climbs over the obstruction.
        $this->assertSame([[5, 5, 6], [8, 4, 6]], $layouts[4]['walls']);
        $this->assertSame([['x' => 6, 'y' => 6], ['x' => 7, 'y' => 6]], $layouts[4]['spikes']);
        $this->assertCount(2, $layouts[4]['apples']);

        // Level 5: the first crate must be pushed into a four-cell shaft, and
        // the portal is back at the start, requiring an upper return route.
        $this->assertSame(['x' => 3, 'y' => 6, 'facing' => 'right', 'length' => 3], $layouts[5]['start']);
        $this->assertSame([['x' => 6, 'y' => 6]], $layouts[5]['blocks']);
        $this->assertSame(['x' => 0, 'y' => 6], $layouts[5]['portal']);
        $this->assertSame([['x' => 5, 'y' => 6], ['x' => 12, 'y' => 6]], $layouts[5]['apples']);
        $this->assertSame([['x' => 9, 'y' => 4]], $layouts[5]['goldenApples']);
        $levelFiveTerrain = collect($layouts[5]['terrain'])->map(fn ($point) => $point['x'] . ':' . $point['y'])->all();
        foreach ([7, 8, 9, 10] as $gapX) {
            $this->assertNotContains($gapX . ':7', $levelFiveTerrain);
        }
        $this->assertContains('4:5', $levelFiveTerrain);
        $this->assertContains('10:5', $levelFiveTerrain);
    }

    public function test_play_route_renders_the_side_view_puzzle_instead_of_the_old_top_down_board(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = $this->player();
        $level = Level::query()->where('number', 1)->firstOrFail();
        UserLevelProgress::create(['user_id' => $player->id, 'level_id' => $level->id, 'unlocked' => true]);

        $this->actingAs($player)
            ->get(route('game.play', $level))
            ->assertOk()
            ->assertSee('puzzleCanvas')
            ->assertSee('Push crates')
            ->assertDontSee('topdown-board');
    }

    public function test_reverse_entry_levels_spawn_the_worm_facing_left(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = $this->player('reverse-spawn@example.test');
        $level = Level::query()->where('number', 8)->firstOrFail();
        UserLevelProgress::create(['user_id' => $player->id, 'level_id' => $level->id, 'unlocked' => true]);

        $this->actingAs($player)->get(route('game.play', $level))->assertOk();
        $session = GameSession::query()->where('user_id', $player->id)->latest('id')->firstOrFail();

        $this->assertSame([
            ['x' => 11, 'y' => 6],
            ['x' => 12, 'y' => 6],
            ['x' => 13, 'y' => 6],
        ], $session->state['worm']);
        $this->assertSame('left', $level->config['sideView']['start']['facing']);
    }

    public function test_legacy_move_endpoint_cannot_mutate_a_side_view_puzzle_session(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = $this->player('legacy-route-guard@example.test');
        $level = Level::query()->where('number', 1)->firstOrFail();
        UserLevelProgress::create(['user_id' => $player->id, 'level_id' => $level->id, 'unlocked' => true]);
        $this->actingAs($player)->get(route('game.play', $level));
        $session = GameSession::query()->where('user_id', $player->id)->latest('id')->firstOrFail();
        $before = $session->state;

        $this->actingAs($player)
            ->postJson(route('game.move', $session), ['direction' => 'right'])
            ->assertStatus(409);

        $this->assertSame($before, $session->fresh()->state);
    }

    public function test_side_view_moves_are_tile_based_and_collecting_an_apple_grows_the_worm(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = $this->player('growth-player@example.test');
        $level = Level::query()->where('number', 1)->firstOrFail();
        UserLevelProgress::create(['user_id' => $player->id, 'level_id' => $level->id, 'unlocked' => true]);

        $page = $this->actingAs($player)->get(route('game.play', $level));
        $sessionId = GameSession::query()->where('user_id', $player->id)->latest('id')->value('id');

        $directions = ['r' => 'right', 'l' => 'left', 'u' => 'up', 'd' => 'down'];
        $collectedApple = false;
        foreach (str_split(SideViewLevelSolutions::for(1)) as $symbol) {
            $response = $this->postJson(route('game.puzzle-move', $sessionId), [
                'direction' => $directions[$symbol],
            ])->assertOk();

            if ($response->json('collected_apple')) {
                $collectedApple = true;
                break;
            }
        }

        $this->assertTrue($collectedApple, 'The redesigned route should collect its apple.');
        $session = GameSession::findOrFail($sessionId);
        $this->assertSame(1, (int) ($session->state['growthPending'] ?? 0), 'Delayed growth should be held as pending when collected during gravity.');
        $this->assertCount(3, $session->state['worm']);

        // Verify the worm actually grows on the next successful move
        $response = $this->postJson(route('game.puzzle-move', $sessionId), [
            'direction' => 'right',
        ])->assertOk();
        $this->assertSame(0, (int) $response->json('state.growthPending'));
        $this->assertCount(4, GameSession::findOrFail($sessionId)->state['worm']);
    }

    public function test_a_crate_pushed_over_a_gap_falls_and_becomes_a_support_tile(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = $this->player('crate-player@example.test');
        $level = Level::query()->where('number', 5)->firstOrFail();
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => [
                'mode' => 'side-view-puzzle',
                'worm' => [['x' => 3, 'y' => 6], ['x' => 2, 'y' => 6], ['x' => 1, 'y' => 6]],
                'apples' => $level->config['sideView']['apples'],
                'goldenApples' => $level->config['sideView']['goldenApples'],
                'blocks' => $level->config['sideView']['blocks'],
                'moves' => 0, 'score' => 0, 'pushes' => 0, 'won' => false, 'lost' => false,
                'switches' => [], 'gatesOpen' => true, 'growthPending' => 0,
            ],
        ]);

        $this->actingAs($player);
        $this->postJson(route('game.puzzle-move', $session), ['direction' => 'right'])->assertOk();
        $this->postJson(route('game.puzzle-move', $session), ['direction' => 'right'])->assertOk()->assertJsonPath('collected_apple', true);
        $this->postJson(route('game.puzzle-move', $session), ['direction' => 'right'])->assertOk()->assertJsonPath('pushed', true);

        $this->assertSame(['x' => 7, 'y' => 7], $session->fresh()->state['blocks'][0]);
        $this->assertSame(1, $session->fresh()->state['pushes']);
        $this->assertSame(4, count($session->fresh()->state['worm']));
    }

    public function test_all_25_authored_levels_have_a_replayable_solution_under_server_rules(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = $this->player('all-level-solutions@example.test');
        // Keep tests and the admin publication validator on the same private approved routes.
        $solutions = SideViewLevelSolutions::all();
        $directions = ['r' => 'right', 'l' => 'left', 'u' => 'up', 'd' => 'down'];

        foreach ($solutions as $number => $solution) {
            $level = Level::query()->where('number', $number)->firstOrFail();
            $layout = $level->config['sideView'];
            $start = $layout['start'];
            [$bodyDx, $bodyDy] = match ($start['facing'] ?? 'right') {
                'left' => [1, 0], 'up' => [0, 1], 'down' => [0, -1], default => [-1, 0],
            };
            $worm = [];
            for ($segment = 0; $segment < $start['length']; $segment++) {
                $worm[] = ['x' => $start['x'] + ($bodyDx * $segment), 'y' => $start['y'] + ($bodyDy * $segment)];
            }
            $session = GameSession::create([
                'user_id' => $player->id,
                'level_id' => $level->id,
                'status' => 'active',
                'state' => [
                    'mode' => 'side-view-puzzle', 'worm' => $worm,
                    'apples' => $layout['apples'], 'goldenApples' => $layout['goldenApples'] ?? [], 'goldenApplesCollected' => 0, 'megaApples' => [], 'mysteryApples' => [],
                    'keys' => [], 'switches' => $layout['switches'] ?? [], 'gatesOpen' => empty($layout['gates']), 'blocks' => $layout['blocks'],
                    'applesCollected' => 0, 'growthPending' => 0, 'moves' => 0, 'score' => 0, 'pushes' => 0,
                    'switchesActivated' => 0, 'gravitySteps' => 0, 'won' => false, 'lost' => false,
                ],
            ]);

            foreach (str_split($solution) as $symbol) {
                if ($session->fresh()->state['won'] ?? false) {
                    break;
                }
                $response = $this->actingAs($player)->postJson(route('game.puzzle-move', $session), [
                    'direction' => $directions[$symbol],
                ])->assertOk();
                $this->assertFalse((bool) $response->json('blocked'), "Level {$number} solution contains a blocked move.");
                $this->assertFalse((bool) $response->json('failed'), "Level {$number} failed while replaying its authored solution.");
            }

            $finalState = $session->fresh()->state;
            $this->assertTrue((bool) $finalState['won'], "Level {$number} did not reach its portal with all apples collected.");
            $this->assertSame([], $finalState['goldenApples'] ?? [], "Level {$number} must collect its milestone Golden Apple.");
            $this->assertSame(count($layout['goldenApples'] ?? []), (int) ($finalState['goldenApplesCollected'] ?? 0));
            if (in_array($number, [8, 12, 14, 17, 19, 23, 24], true)) {
                $this->assertTrue((bool) $finalState['gatesOpen'], "Level {$number} must open its rune gate.");
                $this->assertSame(1, $finalState['switchesActivated']);
            }
            if (in_array($number, [5, 10, 12, 18, 20, 22, 24, 25], true)) {
                $this->assertGreaterThan(0, $finalState['pushes'], "Level {$number} should use a crate in its winning solution.");
            }
        }
    }


    public function test_side_view_milestone_golden_apple_preserves_the_existing_completion_bonus(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = $this->player('sideview-golden@example.test');
        $level = Level::query()->where('number', 5)->firstOrFail();
        UserLevelProgress::create(['user_id' => $player->id, 'level_id' => $level->id, 'unlocked' => true]);

        $this->actingAs($player)->get(route('game.play', $level));
        $session = GameSession::query()->where('user_id', $player->id)->latest('id')->firstOrFail();
        $directions = ['r' => 'right', 'l' => 'left', 'u' => 'up', 'd' => 'down'];

        foreach (str_split(SideViewLevelSolutions::for(5)) as $symbol) {
            $this->postJson(route('game.puzzle-move', $session), ['direction' => $directions[$symbol]])->assertOk();
        }

        $this->assertTrue((bool) ($session->fresh()->state['won'] ?? false));
        $this->assertSame(1, (int) $session->fresh()->state['goldenApplesCollected']);
        $completion = $this->postJson(route('game.complete', $session))
            ->assertOk()
            ->assertJsonPath('golden_apples_collected', 1)
            ->assertJsonPath('golden_bonus_coins', 20);

        $this->assertSame(1, (int) $completion->json('golden_apples_collected'));
    }

    public function test_rune_switch_is_visible_in_layout_and_opens_the_gate_when_collected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = $this->player('rune-switch@example.test');
        $level = Level::query()->where('number', 12)->firstOrFail();
        $layout = $level->config['sideView'];
        $this->assertNotEmpty($layout['switches']);
        $this->assertNotEmpty($layout['gates']);

        [$bodyDx, $bodyDy] = match ($layout['start']['facing'] ?? 'right') {
            'left' => [1, 0], 'up' => [0, 1], 'down' => [0, -1], default => [-1, 0],
        };
        $worm = [];
        for ($i = 0; $i < $layout['start']['length']; $i++) {
            $worm[] = ['x' => $layout['start']['x'] + ($bodyDx * $i), 'y' => $layout['start']['y'] + ($bodyDy * $i)];
        }
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => [
                'mode' => 'side-view-puzzle', 'worm' => $worm,
                'apples' => $layout['apples'], 'blocks' => $layout['blocks'],
                'switches' => $layout['switches'], 'gatesOpen' => false,
                'moves' => 0, 'score' => 0, 'pushes' => 0, 'won' => false, 'lost' => false,
            ],
        ]);

        // Replay the verified route from the right-hand spawn, touching the
        // rune switch before crossing the now-open gate.
        $solution = SideViewLevelSolutions::for(12);
        foreach (str_split($solution) as $symbol) {
            if ($session->fresh()->state['won'] ?? false) break;
            $direction = ['r' => 'right', 'l' => 'left', 'u' => 'up', 'd' => 'down'][$symbol];
            $response = $this->actingAs($player)
                ->postJson(route('game.puzzle-move', $session), ['direction' => $direction])
                ->assertOk();
        }

        $finalState = $session->fresh()->state;
        $this->assertSame([], $finalState['apples']);
        $this->assertSame([], $finalState['switches']);
        $this->assertTrue($finalState['gatesOpen']);
        $this->assertTrue($finalState['won']);
    }

    public function test_spikes_fail_the_run_and_persist_the_failed_status(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = $this->player('spike-player@example.test');
        $level = Level::query()->where('number', 7)->firstOrFail();
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => [
                'mode' => 'side-view-puzzle',
                'worm' => [['x' => 2, 'y' => 6], ['x' => 1, 'y' => 6], ['x' => 0, 'y' => 6]],
                'apples' => $level->config['sideView']['apples'],
                'blocks' => $level->config['sideView']['blocks'],
                'moves' => 0, 'score' => 0, 'pushes' => 0, 'won' => false, 'lost' => false,
            ],
        ]);

        foreach (['right', 'right', 'right'] as $direction) {
            $this->actingAs($player)->postJson(route('game.puzzle-move', $session), ['direction' => $direction])->assertOk();
        }

        $this->actingAs($player)
            ->postJson(route('game.puzzle-move', $session), ['direction' => 'right'])
            ->assertOk()
            ->assertJsonPath('failed', true);

        $this->assertSame('failed', $session->fresh()->status);
        $this->assertTrue((bool) $session->fresh()->state['lost']);
    }

    public function test_portal_does_not_win_while_any_apple_is_still_missing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = $this->player('portal-objective@example.test');
        $level = Level::query()->where('number', 1)->firstOrFail();
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => [
                'mode' => 'side-view-puzzle',
                'worm' => [['x' => 10, 'y' => 6], ['x' => 9, 'y' => 6], ['x' => 8, 'y' => 6]],
                'apples' => $level->config['sideView']['apples'],
                'blocks' => [], 'moves' => 0, 'score' => 0,
                'pushes' => 0, 'won' => false, 'lost' => false,
            ],
        ]);

        $this->actingAs($player)
            ->postJson(route('game.puzzle-move', $session), ['direction' => 'right'])
            ->assertOk()
            ->assertJsonPath('won', false)
            ->assertJsonPath('state.apples.0.x', 9);
    }

    public function test_reaching_the_open_portal_records_completion_through_the_existing_reward_flow(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = $this->player('completion-player@example.test');
        $level = Level::query()->where('number', 1)->firstOrFail();
        UserLevelProgress::create(['user_id' => $player->id, 'level_id' => $level->id, 'unlocked' => true]);
        $this->actingAs($player)->get(route('game.play', $level));
        $session = GameSession::query()->where('user_id', $player->id)->latest('id')->firstOrFail();

        $directions = ['r' => 'right', 'l' => 'left', 'u' => 'up', 'd' => 'down'];
        foreach (str_split(SideViewLevelSolutions::for(1)) as $symbol) {
            $result = $this->postJson(route('game.puzzle-move', $session), [
                'direction' => $directions[$symbol],
            ])->assertOk();
            if ($result->json('won')) {
                break;
            }
        }
        $result->assertJsonPath('won', true);

        $this->postJson(route('game.complete', $session))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('completed', $session->fresh()->status);
        $this->assertDatabaseHas('user_level_progress', [
            'user_id' => $player->id,
            'level_id' => $level->id,
            'completed' => 1,
        ]);
    }
}
