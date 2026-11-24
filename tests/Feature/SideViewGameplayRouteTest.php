<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SideViewGameplayRouteTest extends TestCase
{
    use RefreshDatabase;


    public function test_failure_popup_retry_is_a_plain_post_form_that_resets_the_same_session(): void
    {
        $this->seed();
        $player = User::create([
            'name' => 'Retry Link Player',
            'email' => 'retry-link@example.test',
            'password' => 'secret-password',
        ]);
        $level = \App\Models\Level::query()->where('number', 1)->firstOrFail();
        \App\Models\UserLevelProgress::firstOrCreate(
            ['user_id' => $player->id, 'level_id' => $level->id],
            ['unlocked' => true]
        );

        $this->actingAs($player)
            ->get(route('game.play', $level))
            ->assertOk()
            ->assertSee('id="puzzleErrorRetry"', false)
            ->assertSee('method="POST"', false);

        $session = \App\Models\GameSession::query()
            ->where('user_id', $player->id)
            ->latest('id')
            ->firstOrFail();
        $failedState = $session->state;
        $failedState['lost'] = true;
        $failedState['bonus_moves'] = 5;
        $failedState['extra_moves_claimed'] = true;
        $session->update(['status' => 'failed', 'state' => $failedState]);

        $this->actingAs($player)
            ->post(route('game.restart', $session))
            ->assertRedirect(route('game.session.play', $session));

        $this->actingAs($player)->get(route('game.session.play', $session))->assertOk();
        $restarted = $session->fresh();

        $this->assertSame('active', $restarted->status);
        $this->assertFalse((bool) ($restarted->state['lost'] ?? false));
        $this->assertSame(0, (int) ($restarted->state['moves'] ?? -1));
        $this->assertSame(5, (int) ($restarted->state['bonus_moves'] ?? 0));
        $this->assertTrue((bool) ($restarted->state['extra_moves_claimed'] ?? false));
    }

    public function test_reward_previews_are_hidden_in_the_tutorial_world(): void
    {
        $this->seed();
        $player = User::create([
            'name' => 'Tutorial Preview Player',
            'email' => 'tutorial-preview@example.test',
            'password' => 'secret-password',
        ]);
        $level = \App\Models\Level::query()->where('number', 1)->firstOrFail();
        \App\Models\UserLevelProgress::firstOrCreate(
            ['user_id' => $player->id, 'level_id' => $level->id],
            ['unlocked' => true]
        );

        $this->actingAs($player)->get(route('game.play', $level))
            ->assertOk()
            ->assertDontSee('Optional reward previews')
            ->assertDontSee('Preview +5 moves');
    }

    public function test_later_world_reward_previews_are_optional_and_transparent(): void
    {
        $this->seed();
        $player = User::create([
            'name' => 'Later Preview Player',
            'email' => 'later-preview@example.test',
            'password' => 'secret-password',
        ]);
        $level = \App\Models\Level::query()->where('number', 6)->firstOrFail();
        \App\Models\UserLevelProgress::firstOrCreate(
            ['user_id' => $player->id, 'level_id' => $level->id],
            ['unlocked' => true]
        );

        $this->actingAs($player)->get(route('game.play', $level))
            ->assertOk()
            ->assertSee('Optional reward previews')
            ->assertSee('Development preview only')
            ->assertSee('no real video ad is played')
            ->assertSee('Preview +5 moves')
            ->assertSee('Preview double coins');
    }

    public function test_guests_are_redirected_from_the_real_level_gameplay_route(): void
    {
        $this->seed();

        $this->get('/play/1')->assertRedirect(route('login'));
    }

    public function test_authenticated_players_use_the_puzzle_gameplay_route_not_a_prototype_route(): void
    {
        $this->seed();
        $player = User::create([
            'name' => 'Route Check Player',
            'email' => 'route-check@example.test',
            'password' => 'secret-password',
        ]);

        $this->actingAs($player)
            ->get('/play/1')
            ->assertOk()
            ->assertSee('puzzleCanvas')
            ->assertDontSee('platformerCanvas');

        $this->assertNull(Route::getRoutes()->getByName('game.prototype'));
        $this->assertNull(Route::getRoutes()->getByName('game.platformer.complete'));
    }
}
