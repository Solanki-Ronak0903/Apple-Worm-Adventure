<?php

namespace Tests\Feature;

use App\Game\SideViewLevelSolutions;
use App\Models\GameSession;
use App\Models\Level;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SideViewDifficultyTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $email = 'difficulty-player@example.test'): User
    {
        return User::create([
            'name' => 'Difficulty Tester',
            'email' => $email,
            'password' => 'secret-password',
        ]);
    }

    public function test_all_25_levels_have_move_limits_with_a_practice_buffer_in_every_world(): void
    {
        $this->seed();
        $levels = Level::query()->where('is_published', true)->orderBy('number')->get();

        $this->assertCount(25, $levels);
        foreach ($levels as $level) {
            $layout = $level->config['sideView'];
            $this->assertArrayHasKey('parMoves', $layout, "Level {$level->number} needs a three-star par target.");
            $this->assertArrayHasKey('moveLimit', $layout, "Level {$level->number} needs a hard move budget.");
            $this->assertGreaterThanOrEqual($layout['parMoves'], $layout['moveLimit']);
        }

        $this->assertSame(6, $levels->firstWhere('number', 1)->config['sideView']['moveLimit'] - $levels->firstWhere('number', 1)->config['sideView']['parMoves']);
        $this->assertSame(4, $levels->firstWhere('number', 16)->config['sideView']['moveLimit'] - $levels->firstWhere('number', 16)->config['sideView']['parMoves']);
        $this->assertSame(4, $levels->firstWhere('number', 21)->config['sideView']['moveLimit'] - $levels->firstWhere('number', 21)->config['sideView']['parMoves']);
        $this->assertSame(4, $levels->firstWhere('number', 25)->config['sideView']['moveLimit'] - $levels->firstWhere('number', 25)->config['sideView']['parMoves']);
    }


    public function test_worlds_two_through_five_have_required_detour_fruit_and_verified_par_targets(): void
    {
        $this->seed();
        $levels = Level::query()->whereBetween('number', [6, 25])->orderBy('number')->get();

        $this->assertCount(20, $levels);
        foreach ($levels as $level) {
            $layout = $level->config['sideView'];
            $solution = SideViewLevelSolutions::for((int) $level->number);

            $this->assertGreaterThanOrEqual(2, count($layout['apples']), "Level {$level->number} should include its new required detour fruit.");
            $this->assertNotEmpty($solution, "Level {$level->number} needs a server-approved route.");
            $this->assertSame(strlen($solution), (int) $layout['parMoves'], "Level {$level->number} par must match its verified shortest route.");
            $this->assertGreaterThan(strlen($solution), (int) $layout['moveLimit'] - 1, "Level {$level->number} needs a small practice buffer above par.");
        }
    }

    public function test_selected_mid_and_late_levels_combine_rune_gates_with_their_existing_mechanics(): void
    {
        $this->seed();
        $levels = Level::query()->whereIn('number', [8, 12, 19, 23])->orderBy('number')->get();

        $this->assertCount(4, $levels);
        foreach ($levels as $level) {
            $layout = $level->config['sideView'];
            $this->assertCount(1, $layout['switches'], "Level {$level->number} should have one rune switch.");
            $this->assertCount(1, $layout['gates'], "Level {$level->number} should have one locked gate.");
        }

        $this->assertNotEmpty($levels->firstWhere('number', 12)->config['sideView']['blocks'], 'Side Pocket should retain its crate challenge.');
        $this->assertNotEmpty($levels->firstWhere('number', 23)->config['sideView']['spikes'], 'Cloud Garden should retain its thorn challenge.');
    }

    public function test_levels_17_and_21_have_distinct_late_campaign_puzzles_with_planned_left_entry(): void
    {
        $this->seed();
        $level17 = Level::query()->where('number', 17)->firstOrFail()->config['sideView'];
        $level21 = Level::query()->where('number', 21)->firstOrFail()->config['sideView'];

        $this->assertCount(1, $level17['switches']);
        $this->assertCount(1, $level17['gates']);
        $this->assertSame('right', $level17['start']['facing']);
        $this->assertSame(2, $level17['start']['x']);
        $this->assertCount(4, $level21['apples']);
        $this->assertCount(5, $level21['spikes']);
        $this->assertCount(3, $level21['platforms']);
        $this->assertSame('right', $level21['start']['facing']);
        $this->assertSame(2, $level21['start']['x']);
        $this->assertNotSame($level17['terrain'], $level21['terrain']);
    }

    public function test_gameplay_page_displays_remaining_moves_and_three_star_target(): void
    {
        $this->seed();
        $player = $this->player('move-hud@example.test');

        $this->actingAs($player)->get(route('game.play', ['level' => 1]))
            ->assertOk()
            ->assertSee('MOVES LEFT')
            ->assertSee('3-STAR TARGET')
            ->assertSee('data-move-limit="18"', false);
    }

    public function test_server_fails_the_session_when_player_attempts_a_move_after_the_budget_is_used(): void
    {
        $this->seed();
        $player = $this->player('move-limit-enforced@example.test');

        $this->actingAs($player)->get(route('game.play', ['level' => 1]))->assertOk();
        $session = GameSession::query()->where('user_id', $player->id)->latest('id')->firstOrFail();
        $state = $session->state;
        $state['moves'] = (int) $state['move_limit'];
        $session->update(['state' => $state, 'moves_used' => $state['moves']]);

        $this->actingAsFreshSession($player)
            ->postJson(route('game.puzzle-move', $session), ['direction' => 'right'])
            ->assertOk()
            ->assertJsonPath('failed', true)
            ->assertJsonPath('reason', 'Move limit reached. Restart the level and plan a more efficient route.');

        $this->assertSame('failed', $session->fresh()->status);
        $this->assertTrue((bool) ($session->fresh()->state['lost'] ?? false));
    }

    public function test_server_side_extra_move_allowance_extends_the_budget_without_changing_the_base_limit(): void
    {
        $this->seed();
        $player = $this->player('move-bonus@example.test');

        $this->actingAs($player)->get(route('game.play', ['level' => 1]))->assertOk();
        $session = GameSession::query()->where('user_id', $player->id)->latest('id')->firstOrFail();
        $state = $session->state;
        $state['moves'] = (int) $state['move_limit'];
        $state['bonus_moves'] = 5;
        $session->update(['state' => $state, 'moves_used' => $state['moves']]);

        $response = $this->actingAsFreshSession($player)
            ->postJson(route('game.puzzle-move', $session), ['direction' => 'right'])
            ->assertOk()
            ->assertJsonPath('state.moves', $state['move_limit'] + 1)
            ->assertJsonPath('state.move_limit', $state['move_limit']);

        $this->assertFalse((bool) $response->json('failed'));
        $this->assertSame('active', $session->fresh()->status);
    }
}
