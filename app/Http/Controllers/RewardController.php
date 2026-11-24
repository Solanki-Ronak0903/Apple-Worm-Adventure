<?php

namespace App\Http\Controllers;

use App\Models\AdminSetting;
use App\Models\GameSession;
use App\Models\RewardClaim;
use App\Models\UserLevelReward;
use App\Services\AchievementService;
use App\Services\GameHintService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RewardController extends Controller
{
    private const MAX_REWARDED_HINTS_PER_SESSION = 3;
    private const MAX_SCORE = 100000;

    public function history(Request $request)
    {
        $transactions = $request->user()->transactions()->latest()->paginate(20);
        $claims = RewardClaim::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->take(30)
            ->get();
        $wallet = app(WalletService::class)->getOrCreate($request->user());

        return view('wallet', compact('transactions', 'claims', 'wallet'));
    }

    public function claim(Request $request, GameSession $session)
    {
        abort_unless((int) $session->user_id === (int) $request->user()->id, 403);
        abort_unless($session->level?->is_published, 404, 'This level is not part of the current release.');
        abort_unless(AdminSetting::getValue('rewarded_actions_enabled', true), 403, 'Rewarded actions are currently disabled.');
        abort_unless(
            config('game.rewarded_ads.allow_development_mock', false),
            503,
            'Rewarded ads are not configured on this server yet.'
        );

        $data = $request->validate([
            'action' => 'required|in:revive,double_score,double_coins,extra_moves,hint,booster',
            'claim_token' => 'required|uuid',
        ]);

        $action = $data['action'];
        $levelNumber = (int) ($session->level?->number ?? 0);

        // Reward assists are deliberately introduced after the tutorial worlds.
        // The UI hides them earlier, and the server enforces the same unlocks.
        if (in_array($action, ['extra_moves', 'double_coins'], true)) {
            abort_unless($levelNumber >= 6, 403, 'Optional reward assists unlock from Level 6.');
        }
        if ($action === 'revive') {
            abort_unless($levelNumber >= 11, 403, 'Rewarded revive unlocks from Level 11.');
        }

        // This provider is deliberately a development mock. Production must verify
        // a provider-signed callback before granting any reward.
        return DB::transaction(function () use ($request, $session, $data, $action) {
            $game = GameSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($game->level?->is_published, 404, 'This level is not part of the current release.');

            $existing = RewardClaim::query()
                ->where('claim_token', $data['claim_token'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                abort_unless(
                    (int) $existing->user_id === (int) $request->user()->id
                    && (int) $existing->game_session_id === (int) $game->id,
                    403,
                    'Claim token mismatch.'
                );

                return response()->json([
                    'ok' => false,
                    'message' => 'This reward claim was already processed.',
                ], 409);
            }

            $state = $game->state ?? [];
            $hintPayload = null;

            // One-time power-ups remain consumed even after the player restarts
            // the board in the same server-side session.
            if (in_array($action, ['double_score', 'double_coins', 'extra_moves', 'booster'], true)) {
                abort_if(
                    RewardClaim::query()
                        ->where('game_session_id', $game->id)
                        ->where('action', $action)
                        ->where('status', 'granted')
                        ->exists(),
                    422,
                    'This power-up has already been used in this level session.'
                );
            }

            if ($action === 'revive') {
                abort_unless($game->status === 'failed', 409, 'Revive is only available after a failed game.');
                abort_if($game->revives_used >= 1, 422, 'Revive limit reached for this level.');

                $checkpoint = $state['checkpoint'] ?? null;
                abort_unless(
                    $checkpoint && isset($checkpoint['worm'], $checkpoint['apples']),
                    422,
                    'No valid checkpoint is available.'
                );

                $state['worm'] = $checkpoint['worm'];
                $state['apples'] = $checkpoint['apples'];
                $state['goldenApples'] = $checkpoint['goldenApples'] ?? ($state['goldenApples'] ?? []);
                $state['megaApples'] = $checkpoint['megaApples'] ?? ($state['megaApples'] ?? []);
                $state['mysteryApples'] = $checkpoint['mysteryApples'] ?? ($state['mysteryApples'] ?? []);
                $state['megaApplesCollected'] = (int) ($checkpoint['megaApplesCollected'] ?? ($state['megaApplesCollected'] ?? 0));
                $state['mysteryApplesCollected'] = (int) ($checkpoint['mysteryApplesCollected'] ?? ($state['mysteryApplesCollected'] ?? 0));
                $state['growthPending'] = (int) ($checkpoint['growthPending'] ?? ($state['growthPending'] ?? 0));
                $state['keys'] = $checkpoint['keys'] ?? ($state['keys'] ?? []);
                $state['keysCollected'] = (int) ($checkpoint['keysCollected'] ?? ($state['keysCollected'] ?? 0));
                $state['switches'] = $checkpoint['switches'] ?? ($state['switches'] ?? []);
                $state['switchesActivated'] = (int) ($checkpoint['switchesActivated'] ?? ($state['switchesActivated'] ?? 0));
                $state['goldenApplesCollected'] = (int) ($checkpoint['goldenApplesCollected'] ?? ($state['goldenApplesCollected'] ?? 0));
                $state['moves'] = (int) $checkpoint['moves'];
                $state['score'] = (int) $checkpoint['score'];

                if (($state['mode'] ?? null) === 'side-view-puzzle') {
                    foreach ([
                        'blocks', 'gatesOpen', 'pushes', 'gravitySteps', 'applesCollected',
                    ] as $key) {
                        if (array_key_exists($key, $checkpoint)) {
                            $state[$key] = $checkpoint[$key];
                        }
                    }

                    // If the run ended because its move budget was exhausted,
                    // a revive also grants a small continuation buffer. For a
                    // collision or fall, the player simply resumes pre-move.
                    if (str_contains(strtolower((string) ($state['failure_reason'] ?? '')), 'move limit')) {
                        $state['bonus_moves'] = max(0, (int) ($state['bonus_moves'] ?? 0)) + 5;
                    }
                    $state['revive_claimed'] = true;
                    unset($state['failure_reason']);
                }

                $state['lost'] = false;
                $state['won'] = false;

                $game->update([
                    'status' => 'active',
                    'state' => $state,
                    'score' => $state['score'],
                    'moves_used' => $state['moves'],
                    'revives_used' => $game->revives_used + 1,
                    'finished_at' => null,
                ]);
            } else {
                abort_unless($game->status === 'active', 409, 'This reward is only available during an active game.');

                if ($action === 'double_score') {
                    abort_if(! empty($state['double_score_claimed']), 422, 'Double score has already been used.');
                    $state['score'] = min(self::MAX_SCORE, max(0, (int) ($state['score'] ?? 0)) * 2);
                    $state['double_score_claimed'] = true;
                    $game->update(['score' => $state['score'], 'state' => $state]);
                } elseif ($action === 'double_coins') {
                    abort_if(! empty($state['double_coins_claimed']), 422, 'Double coins has already been used.');
                    abort_if(
                        UserLevelReward::query()
                            ->where('user_id', $request->user()->id)
                            ->where('level_id', $game->level_id)
                            ->where('reward_code', 'level_completion')
                            ->exists(),
                        422,
                        'Double coins is available only on your first clear of this level.'
                    );
                    $state['double_coins_claimed'] = true;
                    $game->update(['state' => $state]);
                } elseif ($action === 'extra_moves') {
                    abort_if(! empty($state['extra_moves_claimed']), 422, 'Extra moves has already been used.');
                    $state['bonus_moves'] = (int) ($state['bonus_moves'] ?? 0) + 5;
                    $state['extra_moves_claimed'] = true;
                    $game->update(['state' => $state]);
                } elseif ($action === 'booster') {
                    abort_if(! empty($state['booster_claimed']), 422, 'Booster has already been used.');
                    $state['booster_active'] = true;
                    $state['booster_claimed'] = true;
                    $game->update(['state' => $state]);
                    $game->increment('boosters_used');
                } elseif ($action === 'hint') {
                    // Count persisted claims instead of trusting resettable JSON state.
                    $used = RewardClaim::query()
                        ->where('game_session_id', $game->id)
                        ->where('action', 'hint')
                        ->where('status', 'granted')
                        ->count();
                    abort_if($used >= self::MAX_REWARDED_HINTS_PER_SESSION, 422, 'You have used all rewarded hints for this level.');

                    // Older active sessions and focused feature tests may not
                    // carry the mode flag in their saved JSON state. The level
                    // definition is authoritative: if it contains a side-view
                    // board, use the side-view-aware hint service.
                    $levelConfig = $game->level?->config ?? [];
                    $isSideView = ($state['mode'] ?? null) === 'side-view-puzzle'
                        || is_array($levelConfig['sideView'] ?? null);

                    $hintPayload = $isSideView
                        ? app(\App\Services\SideViewRewardHintService::class)->suggest($game)
                        : app(GameHintService::class)->suggest($game);
                    abort_unless(
                        ! empty($hintPayload['direction']),
                        422,
                        $hintPayload['hint'] ?? 'No safe hint is available for this board state.'
                    );
                    $state['rewarded_hints_used'] = $used + 1;
                    $game->update(['state' => $state]);
                    $game->increment('hints_used');
                }
            }

            RewardClaim::query()->create([
                'user_id' => $request->user()->id,
                'game_session_id' => $game->id,
                'provider' => 'development_mock',
                'action' => $action,
                'claim_token' => $data['claim_token'],
                'status' => 'granted',
                'reward_payload' => [
                    'simulated' => true,
                    'hint_direction' => $hintPayload['direction'] ?? null,
                ],
                'granted_at' => now(),
            ]);

            $newAchievements = app(AchievementService::class)
                ->recordCounter($request->user(), 'rewards');

            $response = [
                'ok' => true,
                'action' => $action,
                'simulated' => true,
                'achievements_unlocked' => $newAchievements,
                'state' => $game->fresh()->state,
                'status' => $game->fresh()->status,
                'message' => 'Development mock reward granted. No real advertisement was verified.',
            ];

            if ($hintPayload !== null) {
                $response['hint'] = $hintPayload['hint'];
                $response['hint_direction'] = $hintPayload['direction'];
                $response['hint_target'] = $hintPayload['target'];
            }

            return response()->json($response);
        });
    }
}
