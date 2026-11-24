<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Level;
use App\Models\User;
use App\Services\GameHintService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameplaySafetyTest extends TestCase
{
    use RefreshDatabase;

    private function createTailLoopSession(array $apples): array
    {
        $this->seed(DatabaseSeeder::class);

        $player = User::create([
            'name' => 'Tail Collision Player',
            'email' => 'tail-collision@example.test',
            'password' => 'secret-password',
        ]);

        $level = Level::query()->where('number', 1)->firstOrFail();
        $config = $level->config;
        unset($config['sideView'], $config['boardMode'], $config['world']);
        $level->forceFill(['config' => $config])->save();
        $level = $level->fresh();

        // The head at (2,2) can move left into the current tail at (1,2).
        // The body forms a compact loop: head -> (2,3) -> (1,3) -> tail.
        $session = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => [
                'worm' => [
                    ['x' => 2, 'y' => 2],
                    ['x' => 2, 'y' => 3],
                    ['x' => 1, 'y' => 3],
                    ['x' => 1, 'y' => 2],
                ],
                'apples' => $apples,
                'goldenApples' => [],
                'goldenApplesCollected' => 0,
                'moves' => 0,
                'score' => 0,
                'bonus_moves' => 0,
                'won' => false,
                'lost' => false,
            ],
        ]);

        return [$player, $session];
    }

    public function test_moving_into_tail_cell_is_allowed_when_tail_vacates(): void
    {
        [$player, $session] = $this->createTailLoopSession([]);

        $response = $this->actingAs($player)
            ->postJson(route('game.move', $session), ['direction' => 'left'])
            ->assertOk()
            ->assertJsonPath('status', 'active');

        $worm = $response->json('state.worm');

        $this->assertSame(['x' => 1, 'y' => 2], $worm[0]);
        $this->assertCount(4, $worm);
        $this->assertSame('active', $session->fresh()->status);
    }

    public function test_moving_into_tail_cell_with_an_apple_is_a_collision_because_the_tail_does_not_vacate(): void
    {
        [$player, $session] = $this->createTailLoopSession([
            ['x' => 1, 'y' => 2],
        ]);

        $this->actingAs($player)
            ->postJson(route('game.move', $session), ['direction' => 'left'])
            ->assertOk()
            ->assertJsonPath('failed', true)
            ->assertJsonPath('status', 'failed');

        $this->assertSame('failed', $session->fresh()->status);
        $this->assertTrue((bool) $session->fresh()->state['lost']);
    }

    public function test_hint_does_not_recommend_entering_a_tail_cell_that_contains_food(): void
    {
        [, $session] = $this->createTailLoopSession([
            ['x' => 1, 'y' => 2],
        ]);

        $hint = app(GameHintService::class)->suggest($session);

        $this->assertNull($hint['direction']);
    }
}
