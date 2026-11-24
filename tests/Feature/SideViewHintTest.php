<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SideViewHintTest extends TestCase
{
    use RefreshDatabase;

    public function test_side_view_hints_reveal_progressive_clues_then_repeat_the_full_walkthrough_without_a_cap(): void
    {
        $this->seed();
        $player = User::create([
            'name' => 'Progressive Hint Player',
            'email' => 'progressive-hints@example.test',
            'password' => 'secret-password',
        ]);

        $this->actingAsFreshSession($player)->get(route('game.play', ['level' => 1]))->assertOk();
        $session = GameSession::query()->where('user_id', $player->id)->latest('id')->firstOrFail();

        $first = $this->postJson(route('game.hint', $session))
            ->assertOk()
            ->assertJsonPath('stage', 1)
            ->assertJsonPath('total', 4)
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('provider', 'authored_progressive_hints');

        $second = $this->postJson(route('game.hint', $session))
            ->assertOk()
            ->assertJsonPath('stage', 2)
            ->assertJsonPath('has_more', true);

        $third = $this->postJson(route('game.hint', $session))
            ->assertOk()
            ->assertJsonPath('stage', 3)
            ->assertJsonPath('total', 4)
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('exhausted', false);

        $this->assertNotSame($first->json('hint'), $second->json('hint'));
        $this->assertNotSame($second->json('hint'), $third->json('hint'));
        $fourth = $this->postJson(route('game.hint', $session))
            ->assertOk()
            ->assertJsonPath('stage', 4)
            ->assertJsonPath('total', 4)
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('repeatable', true);

        $fifth = $this->postJson(route('game.hint', $session))
            ->assertOk()
            ->assertJsonPath('stage', 4)
            ->assertJsonPath('repeatable', true);

        $this->assertNotSame($third->json('hint'), $fourth->json('hint'));
        $this->assertSame($fourth->json('hint'), $fifth->json('hint'), 'The complete walkthrough must remain available on every later request.');
        $this->assertSame(4, (int) ($session->fresh()->state['hint_stage'] ?? 0));
        $this->assertSame(5, (int) $session->fresh()->hints_used, 'Every requested hint, including a repeated walkthrough, is counted.');
    }

    public function test_side_view_hints_are_scoped_to_the_session_owner(): void
    {
        $this->seed();
        $owner = User::create(['name' => 'Hint Owner', 'email' => 'hint-owner@example.test', 'password' => 'secret-password']);
        $other = User::create(['name' => 'Other Player', 'email' => 'hint-other@example.test', 'password' => 'secret-password']);

        $this->actingAsFreshSession($owner)->get(route('game.play', ['level' => 1]))->assertOk();
        $session = GameSession::query()->where('user_id', $owner->id)->latest('id')->firstOrFail();

        $this->actingAsFreshSession($other)
            ->postJson(route('game.hint', $session))
            ->assertForbidden();

        $this->assertSame(0, (int) ($session->fresh()->state['hint_stage'] ?? 0));
    }
}
