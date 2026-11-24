<?php

namespace App\Http\Middleware;

use App\Models\AdminSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerifiedWhenRequired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && AdminSetting::getValue('require_email_verification', false)
            && ! $user->hasVerifiedEmail()
        ) {
            return redirect()->route('verification.notice')
                ->with('status', 'Please verify your email address before playing.');
        }

        return $next($request);
    }
}
