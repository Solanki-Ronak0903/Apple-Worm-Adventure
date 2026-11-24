<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminAchievementController;
use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\RewardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login')->name('login.store');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,10')->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(['auth', 'auth.session'])->name('logout');

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::get('/dashboard', [GameController::class, 'dashboard'])->name('dashboard');

    // Account settings remain available to unverified users so they can correct their email.
    Route::get('/account', [AccountController::class, 'index'])->name('account');
    Route::patch('/account/profile', [AccountController::class, 'updateProfile'])
        ->middleware('throttle:auth-sensitive')->name('account.profile.update');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])
        ->middleware('throttle:auth-sensitive')->name('account.password.update');
    Route::post('/account/logout-other-sessions', [AccountController::class, 'logoutOtherSessions'])
        ->middleware('throttle:auth-sensitive')->name('account.sessions.destroy');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1')->name('verification.send');

    Route::middleware('email.verified.required')->group(function (): void {
        Route::post('/account/reset-progress', [GameController::class, 'resetProgress'])
            ->middleware('throttle:3,10')->name('account.reset-progress');

        Route::get('/levels', [GameController::class, 'levels'])->name('levels');
        Route::get('/play/{level:number}', [GameController::class, 'play'])->name('game.play');
        Route::get('/game/{session}/play', [GameController::class, 'playSession'])->name('game.session.play');

        Route::post('/game/{session}/move', [GameController::class, 'move'])
            ->middleware('throttle:600,1')->name('game.move');
        Route::post('/game/{session}/puzzle-move', [GameController::class, 'puzzleMove'])
            ->middleware('throttle:600,1')->name('game.puzzle-move');
        Route::post('/game/{session}/restart', [GameController::class, 'restart'])
            ->middleware('throttle:10,1')->name('game.restart');
        Route::get('/game/{session}/state', [GameController::class, 'state'])
            ->middleware('throttle:180,1')->name('game.state');
        Route::post('/game/{session}/complete', [GameController::class, 'complete'])
            ->middleware('throttle:game-completion')->name('game.complete');
        Route::post('/game/{session}/fail', [GameController::class, 'fail'])
            ->middleware('throttle:10,1')->name('game.fail');
        Route::post('/game/{session}/hint', [GameController::class, 'hint'])
            ->middleware('throttle:15,1')->name('game.hint');
        Route::post('/game/{session}/reward', [RewardController::class, 'claim'])
            ->middleware('throttle:15,1')->name('game.reward');

        Route::get('/wallet', [RewardController::class, 'history'])->name('wallet');
        Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard');
    });
});

Route::middleware(['auth', 'auth.session', 'admin', 'throttle:60,1'])
    ->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/', [AdminController::class, 'index'])->name('dashboard');
        Route::get('/players', [AdminController::class, 'players'])->name('players');
        Route::get('/players/{user}', [AdminController::class, 'showPlayer'])->whereNumber('user')->name('players.show');

        Route::get('/levels', [AdminController::class, 'levels'])->name('levels');
        Route::get('/levels/{level}', [AdminController::class, 'showLevel'])->whereNumber('level')->name('levels.show');
        Route::put('/levels/{level}', [AdminController::class, 'updateLevel'])->whereNumber('level')->name('levels.update');
        Route::post('/levels/{level}/validate', [AdminController::class, 'validateLevel'])->whereNumber('level')->name('levels.validate');
        Route::patch('/levels/{level}/publication', [AdminController::class, 'updateLevelPublication'])->whereNumber('level')->name('levels.publication');

        Route::get('/achievements', [AdminAchievementController::class, 'index'])->name('achievements');
        Route::get('/achievements/create', [AdminAchievementController::class, 'create'])->name('achievements.create');
        Route::post('/achievements', [AdminAchievementController::class, 'store'])->name('achievements.store');
        Route::get('/achievements/{achievement}', [AdminAchievementController::class, 'show'])->whereNumber('achievement')->name('achievements.show');
        Route::put('/achievements/{achievement}', [AdminAchievementController::class, 'update'])->whereNumber('achievement')->name('achievements.update');
        Route::patch('/achievements/{achievement}/status', [AdminAchievementController::class, 'toggleStatus'])->whereNumber('achievement')->name('achievements.status');

        Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('analytics');
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings');
        Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index'])->name('audit');
        Route::get('/economy', [AdminController::class, 'economy'])->name('economy');
    });
