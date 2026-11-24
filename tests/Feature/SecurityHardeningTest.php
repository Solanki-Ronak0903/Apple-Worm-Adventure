<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Level;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')
                ->post(route('login.store'), [
                    'email' => 'unknown-user@example.test',
                    'password' => 'incorrect-password',
                ])
                ->assertRedirect('/login')
                ->assertSessionHasErrors('email');
        }

        $this->from('/login')
            ->post(route('login.store'), [
                'email' => 'unknown-user@example.test',
                'password' => 'incorrect-password',
            ])
            ->assertStatus(429);
    }

    public function test_security_headers_are_added_to_web_responses(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    }

    public function test_completion_throttle_is_scoped_to_each_game_session(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $player = User::create([
            'name' => 'Throttle Player',
            'email' => 'throttle-player@example.test',
            'password' => 'test-password',
        ]);

        $level = Level::where('number', 1)->firstOrFail();

        $firstSession = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => [],
        ]);

        $secondSession = GameSession::create([
            'user_id' => $player->id,
            'level_id' => $level->id,
            'status' => 'active',
            'state' => [],
        ]);

        $this->actingAs($player);

        // These requests fail validation but still exercise the route throttle.
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson(route('game.complete', $firstSession))
                ->assertStatus(422);
        }

        $this->postJson(route('game.complete', $firstSession))
            ->assertStatus(429);

        // A different session has its own per-session budget.
        $this->postJson(route('game.complete', $secondSession))
            ->assertStatus(422);
    }
}
