<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountSecurityLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function requestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request, AccountSecurityLogger $logger)
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $email = mb_strtolower($validated['email']);
        $status = Password::sendResetLink(['email' => $email]);

        $user = User::query()->where('email', $email)->first();
        if ($user && $status === Password::RESET_LINK_SENT) {
            $logger->record($request, 'account.password_reset_requested', $user, ['channel' => 'email']);
        }

        // Do not disclose whether an email address is registered.
        return back()->with('status', 'If an account exists for that email address, a password reset link has been sent. Check your inbox.');
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request, AccountSecurityLogger $logger)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:190'],
            'password' => [
                'required', 'confirmed',
                PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $status = Password::reset(
            [
                'email' => mb_strtolower($validated['email']),
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $validated['token'],
            ],
            function (User $user, string $password) use ($request, $logger): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
                $logger->record($request, 'account.password_reset_completed', $user);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Your password has been reset. You can now sign in with your new password.');
        }

        return back()->withErrors(['email' => 'This password reset link is invalid or has expired.'])->withInput($request->only('email'));
    }
}
