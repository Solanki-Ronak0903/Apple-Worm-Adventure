<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Level;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SideViewEngineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_level_route_starts_the_side_view_puzzle_engine_and_saves_a_puzzle_session(): void
    {
        $this->seed();
        $player = User::create([
            'name' => 'Puzzle Integration Player',
            'email' => 'puzzle-integration@example.test',
            'password' => 'secret-password',
        ]);

        $this->actingAs($player)
            ->get(route('game.play', ['level' => 1]))
            ->assertOk()
            ->assertSee('puzzleCanvas')
            ->assertSee('First Bite')
            ->assertSee('puzzle-game.js')
            ->assertSee('puzzleHintPanel')
            ->assertSee('data-hint-url=')
            ->assertDontSee('platformerCanvas')
            ->assertDontSee('Start by following the clear lower path');

        $session = GameSession::query()
            ->where('user_id', $player->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('side-view-puzzle', $session->state['mode'] ?? null);
        $this->assertSame(3, count($session->state['worm'] ?? []));
        $this->assertSame($session->level->config['sideView']['apples'], $session->state['apples']);
    }

    public function test_all_25_published_levels_have_stable_distinct_authored_puzzle_maps_in_five_worlds(): void
    {
        $this->seed();
        $levels = Level::query()->where('is_published', true)->orderBy('number')->get();

        $this->assertCount(25, $levels);
        $signatures = [];
        $worlds = [];

        foreach ($levels as $level) {
            $layout = $level->config['sideView'] ?? null;
            $this->assertIsArray($layout, "Level {$level->number} must use the side-view puzzle catalog.");
            $this->assertSame(14, $layout['width']);
            $this->assertSame(8, $layout['height']);
            $this->assertNotEmpty($layout['terrain']);
            $this->assertNotEmpty($layout['apples']);
            $this->assertArrayHasKey('portal', $layout);
            $this->assertArrayHasKey('parMoves', $layout);
            $this->assertArrayHasKey('moveLimit', $layout);
            $this->assertGreaterThanOrEqual($layout['parMoves'], $layout['moveLimit']);
            $this->assertCount(3, $layout['hintStages'] ?? [], "Level {$level->number} must have three progressive authored hints.");

            $worlds[] = $layout['world']['code'];
            $signatures[] = hash('sha256', json_encode([
                $layout['terrain'], $layout['apples'], $layout['goldenApples'] ?? [],
                $layout['portal'], $layout['blocks'], $layout['spikes'],
                $layout['switches'] ?? [], $layout['gates'] ?? [], $layout['start'],
            ], JSON_THROW_ON_ERROR));
        }

        $this->assertCount(5, array_unique($worlds));
        $this->assertCount(25, array_unique($signatures), 'Every published level must have its own authored board signature.');
    }

    public function test_client_cannot_claim_completion_before_the_server_records_a_win(): void
    {
        $this->seed();
        $player = User::create([
            'name' => 'Puzzle Completion Guard',
            'email' => 'puzzle-completion-guard@example.test',
            'password' => 'secret-password',
        ]);

        $this->actingAs($player)
            ->get(route('game.play', ['level' => 1]))
            ->assertOk();

        $session = GameSession::query()
            ->where('user_id', $player->id)
            ->latest('id')
            ->firstOrFail();

        $this->postJson(route('game.complete', $session))
            ->assertUnprocessable();

        $this->assertSame('active', $session->fresh()->status);
        $this->assertDatabaseMissing('user_level_rewards', [
            'user_id' => $player->id,
            'level_id' => $session->level_id,
            'reward_code' => 'level_completion',
        ]);
    }
}
