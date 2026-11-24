<?php

namespace App\Http\Controllers;

use App\Models\AdminSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AccountSecurityLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister()
    {
        abort_unless(AdminSetting::getValue('allow_registration', true), 403, 'New player registration is currently disabled.');

        return view('auth.register');
    }

    public function register(Request $request, AccountSecurityLogger $logger)
    {
        abort_unless(AdminSetting::getValue('allow_registration', true), 403, 'New player registration is currently disabled.');

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => [
                'required', 'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'password' => 'Use at least 12 characters with uppercase and lowercase letters, a number and a symbol.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            Wallet::create([
                'user_id' => $user->id,
                'coins' => (int) AdminSetting::getValue('new_player_starting_coins', 100),
            ]);

            return $user;
        });

        event(new Registered($user));
        $logger->record($request, 'account.registered', $user);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Your account is ready. Please verify your email address to secure your account.');
    }

    public function login(Request $request, AccountSecurityLogger $logger)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = User::query()->where('email', $credentials['email'])->first();
            $logger->record($request, 'auth.login_failed', $user, ['method' => 'password']);

            return back()
                ->withErrors(['email' => 'The provided credentials do not match our records.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $logger->record($request, 'auth.login_succeeded', $request->user(), ['method' => 'password']);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request, AccountSecurityLogger $logger)
    {
        $user = $request->user();
        $logger->record($request, 'auth.logout', $user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
