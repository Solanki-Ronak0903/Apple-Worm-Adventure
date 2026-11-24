<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Level;
use App\Models\User;
use App\Services\GameHintService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelVarietyTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_release_contains_five_worlds_and_distinct_hand_authored_puzzle_maps(): void
    {
        $this->seed(DatabaseSeeder::class);

        $levels = Level::where('is_published', true)->orderBy('number')->get();
        $this->assertCount(25, $levels);
        $this->assertCount(25, $levels->pluck('title')->unique());
        $this->assertCount(5, $levels->map(fn (Level $level) => $level->config['world']['code'])->unique());
        $this->assertTrue($levels->every(fn (Level $level) => ($level->config['boardMode'] ?? null) === 'side_view_puzzle'));
        $mapSignatures = $levels->map(fn (Level $level) => sha1(json_encode([
            $level->config['sideView']['terrain'],
            $level->config['sideView']['apples'],
            $level->config['sideView']['goldenApples'] ?? [],
            $level->config['sideView']['portal'],
            $level->config['sideView']['blocks'],
            $level->config['sideView']['spikes'],
            $level->config['sideView']['switches'] ?? [],
            $level->config['sideView']['gates'] ?? [],
            $level->config['sideView']['start'],
        ])));
        $this->assertCount(25, $mapSignatures->unique(), 'Each published level must use its own authored puzzle map.');

        foreach ($levels as $level) {
            $config = $level->config;
            $route = $config['plannedRoute'];
            $routeKeys = array_map(fn (array $point) => $point['x'] . ':' . $point['y'], $route);

            $this->assertSame(count($routeKeys), count(array_unique($routeKeys)), "Level {$level->number} repeats a route cell.");
            $this->assertSame($config['wormStart'], $route[0]);
            $this->assertSame($config['portal'], $route[count($route) - 1]);

            for ($index = 1; $index < count($route); $index++) {
                $distance = abs($route[$index]['x'] - $route[$index - 1]['x'])
                    + abs($route[$index]['y'] - $route[$index - 1]['y']);
                $this->assertSame(1, $distance, "Level {$level->number} has a disconnected planned route at step {$index}.");
            }

            foreach (array_merge($config['keys'] ?? [], $config['switches'] ?? [], $config['gates'] ?? [], $config['apples'] ?? [], $config['goldenApples'] ?? [], $config['megaApples'] ?? [], $config['mysteryApples'] ?? []) as $item) {
                $this->assertContains($item['x'] . ':' . $item['y'], $routeKeys);
            }

            // Simulate the complete planned route with the real worm-growth rule.
            $worm = [$route[0]];
            $food = array_merge($config['apples'] ?? [], $config['goldenApples'] ?? [], $config['megaApples'] ?? [], $config['mysteryApples'] ?? []);
            $obstacles = array_map(fn (array $point) => $point['x'] . ':' . $point['y'], array_merge($config['obstacles'] ?? [], $config['thorns'] ?? [], $config['mudPits'] ?? []));
            $growthPending = 0;
            foreach (array_slice($route, 1) as $step => $next) {
                $nextKey = $next['x'] . ':' . $next['y'];
                $this->assertNotContains($nextKey, $obstacles, "Level {$level->number} route enters a hazard.");
                $growth = 0;
                foreach (($config['apples'] ?? []) as $item) if ($item['x'] === $next['x'] && $item['y'] === $next['y']) $growth += 1;
                foreach (($config['goldenApples'] ?? []) as $item) if ($item['x'] === $next['x'] && $item['y'] === $next['y']) $growth += 1;
                foreach (($config['megaApples'] ?? []) as $item) if ($item['x'] === $next['x'] && $item['y'] === $next['y']) $growth += 3;
                foreach (($config['mysteryApples'] ?? []) as $item) if ($item['x'] === $next['x'] && $item['y'] === $next['y'] && ($item['effect'] ?? '') === 'extra_growth') $growth += 2;
                $willGrow = $growth > 0 || $growthPending > 0;
                $bodyToCheck = $willGrow ? $worm : array_slice($worm, 0, max(0, count($worm) - 1));
                $this->assertFalse(
                    collect($bodyToCheck)->contains(fn (array $part) => $part['x'] === $next['x'] && $part['y'] === $next['y']),
                    "Level {$level->number} planned route collides with its worm at step " . ($step + 1) . '.'
                );
                array_unshift($worm, $next);
                $growthPending += $growth;
                if ($growthPending > 0) $growthPending--;
                else array_pop($worm);
            }
        }
    }

    public function test_published_levels_use_the_new_side_view_puzzle_boards(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (Level::query()->where('is_published', true)->get() as $level) {
            $config = $level->config;
            $this->assertSame('side_view_puzzle', $config['boardMode'] ?? null);
            $this->assertSame(14, $config['sideView']['width']);
            $this->assertSame(8, $config['sideView']['height']);
            $this->assertSame($config['grid']['rows'], $level->grid_rows);
            $this->assertSame($config['grid']['cols'], $level->grid_cols);
        }
    }

    public function test_key_unlocks_gate_and_locked_gate_does_not_end_the_run(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create([
            'name' => 'Key Gate Player',
            'email' => 'key-gate@example.test',
            'password' => 'secret-password',
        ]);
        $level = $this->useLegacyBoard(Level::where('number', 11)->firstOrFail());
        $config = $level->config;
        $route = $config['plannedRoute'];
        $key = $config['keys'][0];
        $gate = $config['gates'][0];
        $keyIndex = $this->routeIndex($route, $key);
        $gateIndex = $this->routeIndex($route, $gate);
        $this->assertLessThan($gateIndex, $keyIndex, 'The key must be before the gate on the route.');

        $gateApproach = $route[$gateIndex - 1];
        $lockedSession = $this->makeSession($player, $level, [
            'worm' => [$gateApproach],
            'apples' => [],
            'goldenApples' => [],
            'keys' => [$key],
            'keysCollected' => 0,
            'switches' => [],
            'switchesActivated' => 0,
            'goldenApplesCollected' => 0,
            'moves' => 0,
            'score' => 0,
        ]);

        $this->actingAs($player)
            ->postJson(route('game.move', $lockedSession), ['direction' => $this->directionBetween($gateApproach, $gate)])
            ->assertOk()
            ->assertJsonPath('blocked', true)
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('state.moves', 0);

        $keyApproach = $route[$keyIndex - 1];
        $keySession = $this->makeSession($player, $level, [
            'worm' => [$keyApproach],
            'apples' => [],
            'goldenApples' => [],
            'keys' => [$key],
            'keysCollected' => 0,
            'switches' => [],
            'switchesActivated' => 0,
            'goldenApplesCollected' => 0,
            'moves' => 0,
            'score' => 0,
        ]);

        $this->postJson(route('game.move', $keySession), ['direction' => $this->directionBetween($keyApproach, $key)])
            ->assertOk()
            ->assertJsonPath('state.keys', [])
            ->assertJsonPath('state.keysCollected', 1)
            ->assertJsonPath('state.score', 25);

        // Follow the valid route after the key: the formerly locked gate is now passable.
        for ($index = $keyIndex + 1; $index <= $gateIndex; $index++) {
            $response = $this->postJson(route('game.move', $keySession), [
                'direction' => $this->directionBetween($route[$index - 1], $route[$index]),
            ])->assertOk();
            $this->assertFalse((bool) $response->json('blocked'));
            $this->assertSame('active', $response->json('status'));
        }
    }

    public function test_switch_activates_and_opens_its_gate(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create([
            'name' => 'Switch Player',
            'email' => 'switch-player@example.test',
            'password' => 'secret-password',
        ]);
        $level = $this->useLegacyBoard(Level::where('number', 12)->firstOrFail());
        $config = $level->config;
        $route = $config['plannedRoute'];
        $switch = $config['switches'][0];
        $gate = $config['gates'][0];
        $switchIndex = $this->routeIndex($route, $switch);
        $gateIndex = $this->routeIndex($route, $gate);
        $this->assertLessThan($gateIndex, $switchIndex, 'The switch must be before the gate on the route.');

        $switchApproach = $route[$switchIndex - 1];
        $session = $this->makeSession($player, $level, [
            'worm' => [$switchApproach],
            'apples' => [],
            'goldenApples' => [],
            'megaApples' => [],
            'mysteryApples' => [],
            'megaApplesCollected' => 0,
            'mysteryApplesCollected' => 0,
            'growthPending' => 0,
            'keys' => [],
            'keysCollected' => 0,
            'switches' => [$switch],
            'switchesActivated' => 0,
            'goldenApplesCollected' => 0,
            'moves' => 0,
            'score' => 0,
        ]);

        $this->actingAs($player)
            ->postJson(route('game.move', $session), ['direction' => $this->directionBetween($switchApproach, $switch)])
            ->assertOk()
            ->assertJsonPath('state.switches', [])
            ->assertJsonPath('state.switchesActivated', 1)
            ->assertJsonPath('state.score', 20);

        for ($index = $switchIndex + 1; $index <= $gateIndex; $index++) {
            $response = $this->postJson(route('game.move', $session), [
                'direction' => $this->directionBetween($route[$index - 1], $route[$index]),
            ])->assertOk();
            $this->assertFalse((bool) $response->json('blocked'));
            $this->assertSame('active', $response->json('status'));
        }
    }

    public function test_hint_prioritizes_a_required_key_before_apples(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create([
            'name' => 'Hint Player',
            'email' => 'key-hint@example.test',
            'password' => 'secret-password',
        ]);
        $level = Level::where('number', 11)->firstOrFail();
        $config = $level->config;
        $session = $this->makeSession($player, $level, [
            'worm' => [$config['wormStart']],
            'apples' => $config['apples'],
            'goldenApples' => $config['goldenApples'],
            'keys' => $config['keys'],
            'keysCollected' => 0,
            'switches' => $config['switches'],
            'switchesActivated' => 0,
            'goldenApplesCollected' => 0,
            'moves' => 0,
            'score' => 0,
        ]);

        $hint = app(GameHintService::class)->suggest($session);

        $this->assertSame('key', $hint['target']);
        $this->assertContains($hint['direction'], ['up', 'down', 'left', 'right']);

        $switchLevel = Level::where('number', 12)->firstOrFail();
        $switchConfig = $switchLevel->config;
        $switchSession = $this->makeSession($player, $switchLevel, [
            'worm' => [$switchConfig['wormStart']],
            'apples' => $switchConfig['apples'],
            'goldenApples' => $switchConfig['goldenApples'],
            'keys' => [],
            'keysCollected' => 0,
            'switches' => $switchConfig['switches'],
            'switchesActivated' => 0,
            'goldenApplesCollected' => 0,
            'moves' => 0,
            'score' => 0,
        ]);
        $switchHint = app(GameHintService::class)->suggest($switchSession);
        $this->assertSame('switch', $switchHint['target']);
        $this->assertContains($switchHint['direction'], ['up', 'down', 'left', 'right']);
    }

    public function test_mega_apple_adds_three_segments_and_ten_points(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create(['name' => 'Mega Player', 'email' => 'mega-player@example.test', 'password' => 'secret-password']);
        $level = $this->useLegacyBoard(Level::where('number', 7)->firstOrFail());
        $config = $level->config;
        $mega = $config['megaApples'][0];
        $route = $config['plannedRoute'];
        $index = $this->routeIndex($route, $mega);
        $from = $route[$index - 1];
        $session = $this->makeSession($player, $level, [
            'worm' => [$from], 'apples' => [], 'goldenApples' => [], 'megaApples' => [$mega], 'mysteryApples' => [],
            'megaApplesCollected' => 0, 'mysteryApplesCollected' => 0, 'growthPending' => 0,
            'keys' => [], 'keysCollected' => 0, 'switches' => [], 'switchesActivated' => 0,
            'goldenApplesCollected' => 0, 'moves' => 0, 'score' => 0,
        ]);

        $this->actingAs($player)
            ->postJson(route('game.move', $session), ['direction' => $this->directionBetween($from, $mega)])
            ->assertOk()
            ->assertJsonPath('state.megaApples', [])
            ->assertJsonPath('state.megaApplesCollected', 1)
            ->assertJsonPath('state.score', 10)
            ->assertJsonPath('state.growthPending', 2);
        $this->assertCount(2, $session->fresh()->state['worm']);
    }

    public function test_mystery_apple_applies_its_configured_bonus_moves_effect(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create(['name' => 'Mystery Player', 'email' => 'mystery-player@example.test', 'password' => 'secret-password']);
        $level = $this->useLegacyBoard(Level::where('number', 8)->firstOrFail());
        $config = $level->config;
        $mystery = $config['mysteryApples'][0];
        $route = $config['plannedRoute'];
        $index = $this->routeIndex($route, $mystery);
        $from = $route[$index - 1];
        $session = $this->makeSession($player, $level, [
            'worm' => [$from], 'apples' => [], 'goldenApples' => [], 'megaApples' => [], 'mysteryApples' => [$mystery],
            'megaApplesCollected' => 0, 'mysteryApplesCollected' => 0, 'growthPending' => 0,
            'keys' => [], 'keysCollected' => 0, 'switches' => [], 'switchesActivated' => 0,
            'goldenApplesCollected' => 0, 'moves' => 0, 'score' => 0,
        ]);

        $this->actingAs($player)
            ->postJson(route('game.move', $session), ['direction' => $this->directionBetween($from, $mystery)])
            ->assertOk()
            ->assertJsonPath('state.mysteryApples', [])
            ->assertJsonPath('state.mysteryApplesCollected', 1)
            ->assertJsonPath('state.score', 10)
            ->assertJsonPath('state.bonus_moves', 5);
    }

    public function test_mud_pit_blocks_without_failing_or_spending_a_move(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create(['name' => 'Mud Player', 'email' => 'mud-player@example.test', 'password' => 'secret-password']);
        $level = $this->useLegacyBoard(Level::where('number', 1)->firstOrFail());
        $config = $level->config;
        $config['mudPits'] = [['x' => 2, 'y' => 2]];
        $level->update(['config' => $config]);
        $session = $this->makeSession($player, $level, [
            'worm' => [['x' => 1, 'y' => 2]], 'apples' => [], 'goldenApples' => [], 'megaApples' => [], 'mysteryApples' => [],
            'megaApplesCollected' => 0, 'mysteryApplesCollected' => 0, 'growthPending' => 0,
            'keys' => [], 'keysCollected' => 0, 'switches' => [], 'switchesActivated' => 0,
            'goldenApplesCollected' => 0, 'moves' => 0, 'score' => 0,
        ]);

        $this->actingAs($player)
            ->postJson(route('game.move', $session), ['direction' => 'right'])
            ->assertOk()
            ->assertJsonPath('blocked', true)
            ->assertJsonPath('state.moves', 0)
            ->assertJsonPath('status', 'active');
    }

    public function test_thorns_fail_the_run(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create(['name' => 'Thorn Player', 'email' => 'thorn-player@example.test', 'password' => 'secret-password']);
        $level = $this->useLegacyBoard(Level::where('number', 1)->firstOrFail());
        $config = $level->config;
        $config['thorns'] = [['x' => 2, 'y' => 2]];
        $level->update(['config' => $config]);
        $session = $this->makeSession($player, $level, [
            'worm' => [['x' => 1, 'y' => 2]], 'apples' => [], 'goldenApples' => [], 'megaApples' => [], 'mysteryApples' => [],
            'megaApplesCollected' => 0, 'mysteryApplesCollected' => 0, 'growthPending' => 0,
            'keys' => [], 'keysCollected' => 0, 'switches' => [], 'switchesActivated' => 0,
            'goldenApplesCollected' => 0, 'moves' => 0, 'score' => 0,
        ]);

        $this->actingAs($player)
            ->postJson(route('game.move', $session), ['direction' => 'right'])
            ->assertOk()
            ->assertJsonPath('failed', true)
            ->assertJsonPath('status', 'failed');
    }

    public function test_completion_endpoint_rejects_a_won_state_below_required_length(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create(['name' => 'Completion Guard', 'email' => 'completion-guard@example.test', 'password' => 'secret-password']);
        $level = $this->useLegacyBoard(Level::where('number', 5)->firstOrFail());
        $session = $this->makeSession($player, $level, [
            'worm' => [$level->config['portal']],
            'apples' => [],
            'goldenApples' => [],
            'megaApples' => [],
            'mysteryApples' => [],
            'keys' => [],
            'switches' => [],
            'moves' => 1,
            'score' => 100,
            'won' => true,
            'lost' => false,
        ]);

        $this->actingAs($player)
            ->postJson(route('game.complete', $session))
            ->assertStatus(422);

        $this->assertSame('active', $session->fresh()->status);
    }

    public function test_portal_requires_the_configured_worm_length(): void
    {
        $this->seed(DatabaseSeeder::class);
        $player = User::create(['name' => 'Length Player', 'email' => 'length-player@example.test', 'password' => 'secret-password']);
        $level = $this->useLegacyBoard(Level::where('number', 5)->firstOrFail());
        $config = $level->config;
        $route = $config['plannedRoute'];
        $from = $route[count($route) - 2];
        $portal = $config['portal'];
        $session = $this->makeSession($player, $level, [
            'worm' => [$from], 'apples' => [], 'goldenApples' => [], 'megaApples' => [], 'mysteryApples' => [],
            'megaApplesCollected' => 0, 'mysteryApplesCollected' => 0, 'growthPending' => 0,
            'keys' => [], 'keysCollected' => 0, 'switches' => [], 'switchesActivated' => 0,
            'goldenApplesCollected' => 0, 'moves' => 0, 'score' => 0,
        ]);

        $this->actingAs($player)
            ->postJson(route('game.move', $session), ['direction' => $this->directionBetween($from, $portal)])
            ->assertOk()
            ->assertJsonPath('blocked', true)
            ->assertJsonPath('state.moves', 0)
            ->assertJsonPath('state.won', false);
    }

    /** Keep legacy-engine regression tests explicit after release levels switch to side-view. */
    private function useLegacyBoard(Level $level): Level
    {
        $config = $level->config;
        unset($config['sideView'], $config['boardMode'], $config['world']);
        $level->forceFill(['config' => $config])->save();

        return $level->fresh();
    }

    private function makeSession(User $player, Level $level, array $state): GameSession
    {
        $state += [
            'megaApples' => [],
            'mysteryApples' => [],
            'megaApplesCollected' => 0,
            'mysteryApplesCollected' => 0,
            'growthPending' => 0,
            'keys' => [],
            'keysCollected' => 0,
            'switches' => [],
            'switchesActivated' => 0,
            'goldenApplesCollected' => 0,
            'bonus_moves' => 0,
            'won' => false,
            'lost' => false,
            'double_score_claimed' => false,
            'double_coins_claimed' => false,
            'extra_moves_claimed' => false,
            'booster_claimed' => false,
            'rewarded_hints_used' => 0,
            'booster_active' => false,
        ];
        $state['checkpoint'] = [
            'worm' => $state['worm'],
            'apples' => $state['apples'],
            'goldenApples' => $state['goldenApples'],
            'megaApples' => $state['megaApples'],
            'mysteryApples' => $state['mysteryApples'],
            'megaApplesCollected' => $state['megaApplesCollected'],
            'mysteryApplesCollected' => $state['mysteryApplesCollected'],
            'growthPending' => $state['growthPending'],
            'keys' => $state['keys'],
            'keysCollected' => $state['keysCollected'],
            'switches' => $state['switches'],
            'switchesActivated' => $state['switchesActivated'],
            'goldenApplesCollected' => $state['goldenApplesCollected'],
            'moves' => $state['moves'],
            'score' => $state['score'],
        ];

        return GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => $state,
        ]);
    }

    private function routeIndex(array $route, array $point): int
    {
        foreach ($route as $index => $candidate) {
            if ($candidate['x'] === $point['x'] && $candidate['y'] === $point['y']) return $index;
        }
        $this->fail('The configured key or gate is not on the planned route.');
    }

    private function directionBetween(array $from, array $to): string
    {
        if ($to['x'] > $from['x']) return 'right';
        if ($to['x'] < $from['x']) return 'left';
        if ($to['y'] > $from['y']) return 'down';
        if ($to['y'] < $from['y']) return 'up';
        $this->fail('Cannot derive a direction for identical route points.');
    }
}
