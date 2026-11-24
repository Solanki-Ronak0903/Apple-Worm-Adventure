<?php

namespace App\Http\Controllers;

use App\Services\AccountSecurityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        return view('account.index', [
            'securityEvents' => $request->user()->securityEvents()->latest('created_at')->limit(12)->get(),
        ]);
    }

    public function updateProfile(Request $request, AccountSecurityLogger $logger)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', 'lowercase', 'unique:users,email,' . $user->id],
            'current_password' => ['required', 'current_password:web'],
        ]);

        $emailChanged = mb_strtolower($validated['email']) !== mb_strtolower($user->email);
        $before = ['name' => $user->name, 'email' => $user->email];

        DB::transaction(function () use ($user, $validated, $emailChanged): void {
            $user->name = $validated['name'];
            $user->email = mb_strtolower($validated['email']);
            if ($emailChanged) {
                $user->email_verified_at = null;
            }
            $user->save();
        });

        $after = ['name' => $user->fresh()->name, 'email' => $user->fresh()->email];
        $changed = array_keys(array_diff_assoc($after, $before));
        $logger->record($request, $emailChanged ? 'account.email_changed' : 'account.profile_updated', $user, [
            'fields' => $changed,
        ]);

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
            return redirect()->route('account')->with('status', 'Email address updated. Please verify your new email address.');
        }

        return redirect()->route('account')->with('status', 'Your profile has been updated.');
    }

    public function updatePassword(Request $request, AccountSecurityLogger $logger)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => [
                'required', 'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'password' => 'Use at least 12 characters with uppercase and lowercase letters, a number and a symbol.',
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $validated['password']])->save();

        // Rotate the remember token and invalidate other authenticated sessions.
        Auth::logoutOtherDevices($validated['password']);
        $request->session()->regenerate();

        $logger->record($request, 'account.password_changed', $user);

        return redirect()->route('account')->with('status', 'Password changed. Other remembered devices have been signed out.');
    }

    public function logoutOtherSessions(Request $request, AccountSecurityLogger $logger)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
        ]);

        Auth::logoutOtherDevices($validated['current_password']);
        $request->session()->regenerate();
        $logger->record($request, 'account.other_sessions_revoked', $request->user());

        return redirect()->route('account')->with('status', 'Other remembered devices have been signed out.');
    }
}
