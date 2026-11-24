<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('auth-login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email', 'unknown')));

            return [
                Limit::perMinute(5)->by('login:account:' . hash('sha256', $email)),
                Limit::perMinute(20)->by('login:ip:' . hash('sha256', (string) $request->ip())),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email', 'unknown')));

            return [
                Limit::perMinutes(10, 3)->by('password-reset:account:' . hash('sha256', $email)),
                Limit::perMinutes(10, 10)->by('password-reset:ip:' . hash('sha256', (string) $request->ip())),
            ];
        });

        RateLimiter::for('auth-sensitive', function (Request $request) {
            return Limit::perMinute(5)->by('auth-sensitive:' . ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('game-completion', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier() ?? $request->ip();
            $session = $request->route('session');
            $sessionId = is_object($session) && method_exists($session, 'getKey')
                ? $session->getKey()
                : (string) $session;

            return [
                Limit::perMinute(10)->by('game-completion:session:' . $userId . ':' . $sessionId),
                Limit::perMinute(30)->by('game-completion:user:' . $userId),
            ];
        });
    }
}
