<?php

namespace App\Http\Controllers;

use App\Services\AccountSecurityLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice()
    {
        return view('auth.verify-email');
    }

    public function verify(Request $request, AccountSecurityLogger $logger)
    {
        $user = $request->user();
        abort_unless((string) $request->route('id') === (string) $user->getKey(), 403);

        if (! $user->hasVerifiedEmail()) {
            abort_unless(hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification())), 403);
            $user->markEmailAsVerified();
            event(new Verified($user));
            $logger->record($request, 'account.email_verified', $user);
        }

        return redirect()->route('account')->with('status', 'Email address successfully verified.');
    }

    public function resend(Request $request, AccountSecurityLogger $logger)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account')->with('status', 'Your email address is already verified.');
        }

        $request->user()->sendEmailVerificationNotification();
        $logger->record($request, 'account.email_verification_resent', $request->user());

        return back()->with('status', 'A fresh verification link has been sent to your email address.');
    }
}
