<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\Level;
use App\Models\User;
use App\Services\AccountSecurityLogger;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function player(string $email = 'security-player@example.test'): User
    {
        return User::create([
            'name' => 'Security Player',
            'email' => $email,
            'password' => 'OldSecurePass123!',
        ]);
    }

    public function test_registration_requires_a_strong_password_and_sends_email_verification(): void
    {
        Notification::fake();

        $this->from(route('register'))->post(route('register.store'), [
            'name' => 'New Secure Player',
            'email' => 'new-secure-player@example.test',
            'password' => 'weak-password',
            'password_confirmation' => 'weak-password',
        ])->assertRedirect(route('register'))->assertSessionHasErrors('password');

        $this->post(route('register.store'), [
            'name' => 'New Secure Player',
            'email' => 'new-secure-player@example.test',
            'password' => 'StrongPlayerPass123!',
            'password_confirmation' => 'StrongPlayerPass123!',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'new-secure-player@example.test')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_login_normalizes_email_and_records_failed_attempts_without_disclosing_account_existence(): void
    {
        $user = $this->player('CaseSensitive@example.test');

        $this->post(route('login.store'), [
            'email' => 'CASESENSITIVE@EXAMPLE.TEST',
            'password' => 'OldSecurePass123!',
        ])->assertRedirect(route('dashboard'));

        auth()->logout();

        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'unknown-security-user@example.test',
            'password' => 'not-the-password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertDatabaseHas('account_security_events', [
            'user_id' => null,
            'event' => 'auth.login_failed',
        ]);
    }

    public function test_account_page_shows_security_settings_and_recent_activity(): void
    {
        $user = $this->player();
        app(AccountSecurityLogger::class)->record(request(), 'auth.login_succeeded', $user);

        $this->actingAs($user)->get(route('account'))
            ->assertOk()
            ->assertSee('Account &')
            ->assertSee('Change password')
            ->assertSee('Sign out other devices')
            ->assertSee('Recent security activity');
    }

    public function test_profile_email_change_requires_current_password_and_sends_new_verification(): void
    {
        Notification::fake();
        $user = $this->player();
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user)->patch(route('account.profile.update'), [
            'name' => 'Updated Player',
            'email' => 'updated-player@example.test',
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password');

        $this->patch(route('account.profile.update'), [
            'name' => 'Updated Player',
            'email' => 'updated-player@example.test',
            'current_password' => 'OldSecurePass123!',
        ])->assertRedirect(route('account'));

        $user->refresh();
        $this->assertSame('updated-player@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertDatabaseHas('account_security_events', [
            'user_id' => $user->id,
            'event' => 'account.email_changed',
        ]);
    }

    public function test_authenticated_session_is_invalidated_after_password_changes(): void
    {
        $user = $this->player();

        $this->actingAs($user)->get(route('account'))->assertOk();

        $user->forceFill(['password' => 'NewSecurePass456!'])->save();

        $this->get(route('account'))->assertRedirect(route('login'));
    }

    public function test_password_can_be_changed_only_with_current_password_and_strong_new_password(): void
    {
        $user = $this->player();

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'NewStrongPassword456!',
            'password_confirmation' => 'NewStrongPassword456!',
        ])->assertSessionHasErrors('current_password');

        $this->put(route('account.password.update'), [
            'current_password' => 'OldSecurePass123!',
            'password' => 'NewStrongPassword456!',
            'password_confirmation' => 'NewStrongPassword456!',
        ])->assertRedirect(route('account'));

        $this->assertTrue(Hash::check('NewStrongPassword456!', $user->fresh()->password));
        $this->assertDatabaseHas('account_security_events', [
            'user_id' => $user->id,
            'event' => 'account.password_changed',
        ]);
    }

    public function test_password_reset_request_uses_generic_response_and_sends_notification_for_known_email(): void
    {
        Notification::fake();
        $user = $this->player();

        $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ])->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an account exists for that email address, a password reset link has been sent. Check your inbox.');

        Notification::assertSentTo($user, ResetPassword::class);

        $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'not-registered@example.test',
        ])->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an account exists for that email address, a password reset link has been sent. Check your inbox.');
    }

    public function test_password_reset_token_changes_password_and_remember_token(): void
    {
        $user = $this->player();
        $oldRememberToken = $user->remember_token;
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'ResetPassword789!',
            'password_confirmation' => 'ResetPassword789!',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('ResetPassword789!', $user->password));
        $this->assertNotSame($oldRememberToken, $user->remember_token);
        $this->assertDatabaseHas('account_security_events', [
            'user_id' => $user->id,
            'event' => 'account.password_reset_completed',
        ]);
    }

    public function test_signed_email_verification_marks_the_current_users_email_verified(): void
    {
        $user = $this->player();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect(route('account'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('account_security_events', [
            'user_id' => $user->id,
            'event' => 'account.email_verified',
        ]);
    }

    public function test_email_verification_can_be_required_for_game_routes_by_admin_setting(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $user = $this->player();
        AdminSetting::query()->create([
            'key' => 'require_email_verification',
            'group' => 'accounts',
            'value' => json_encode(true),
        ]);

        $this->actingAs($user)->get('/play/1')->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('Verify your');

        $user->markEmailAsVerified();
        $this->get('/play/1')->assertOk();
    }

    public function test_player_can_sign_out_other_devices_after_password_confirmation(): void
    {
        $user = $this->player();

        $this->actingAs($user)->post(route('account.sessions.destroy'), [
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors('current_password');

        $this->post(route('account.sessions.destroy'), [
            'current_password' => 'OldSecurePass123!',
        ])->assertRedirect(route('account'));

        $this->assertDatabaseHas('account_security_events', [
            'user_id' => $user->id,
            'event' => 'account.other_sessions_revoked',
        ]);
    }

    public function test_verification_and_account_security_pages_have_private_no_store_headers(): void
    {
        $user = $this->player();
        $this->actingAs($user)->get(route('account'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
