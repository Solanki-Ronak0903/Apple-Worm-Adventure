<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AuthController,
    GameController,
    RewardController,
    LeaderboardController,
    AdminController
};

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', fn() => view('welcome'))->name('home');


/*
|--------------------------------------------------------------------------
| Guest Routes - Login & Registration
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {

    Route::get('/login', [AuthController::class, 'showLogin'])
        ->name('login');

    Route::post('/login', [AuthController::class, 'login'])
        ->name('login.store');

    Route::get('/register', [AuthController::class, 'showRegister'])
        ->name('register');

    Route::post('/register', [AuthController::class, 'register'])
        ->name('register.store');

});


/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');


/*
|--------------------------------------------------------------------------
| Authenticated Player Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [GameController::class, 'dashboard'])
        ->name('dashboard');

    // Reset only this player's progress; current password is required.
    Route::post('/account/reset-progress', [GameController::class, 'resetProgress'])
        ->middleware('throttle:3,10')
        ->name('account.reset-progress');

    // Level selection
    Route::get('/levels', [GameController::class, 'levels'])
        ->name('levels');

    // Start a game
    Route::get('/play/{level:number}', [GameController::class, 'play'])
        ->name('game.play');


    /*
    |--------------------------------------------------------------------------
    | Game Actions
    |--------------------------------------------------------------------------
    */

    // Server-side worm movement
    Route::post('/game/{session}/move', [GameController::class, 'move'])
        ->name('game.move');

    Route::post('/game/{session}/restart', [GameController::class, 'restart'])
        ->name('game.restart');

    Route::get('/game/{session}/state', [GameController::class, 'state'])
        ->name('game.state');

    // Complete a level
    Route::post('/game/{session}/complete', [GameController::class, 'complete'])
        ->name('game.complete');

    // Fail a game
    Route::post('/game/{session}/fail', [GameController::class, 'fail'])
        ->name('game.fail');

    // Request a hint
    Route::post('/game/{session}/hint', [GameController::class, 'hint'])
        ->name('game.hint');

    // Claim a reward
    Route::post('/game/{session}/reward', [RewardController::class, 'claim'])
        ->name('game.reward');


    /*
    |--------------------------------------------------------------------------
    | Player Wallet & Leaderboard
    |--------------------------------------------------------------------------
    */

    Route::get('/wallet', [RewardController::class, 'history'])
        ->name('wallet');

    Route::get('/leaderboard', [LeaderboardController::class, 'index'])
        ->name('leaderboard');

});


/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Admin dashboard
        Route::get('/', [AdminController::class, 'index'])
            ->name('dashboard');

        // Game economy management
        Route::get('/economy', [AdminController::class, 'economy'])
            ->name('economy');

    });