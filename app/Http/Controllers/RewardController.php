<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\RewardClaim;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AchievementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RewardController extends Controller
{
    public function history(Request $request)
    {
        $transactions = $request->user()->transactions()->latest()->paginate(20);
        $claims = RewardClaim::where('user_id', $request->user()->id)->latest()->take(30)->get();
        $wallet = $request->user()->wallet()->firstOrCreate([], ['coins' => 100]);
        return view('wallet', compact('transactions', 'claims', 'wallet'));
    }

    public function claim(Request $request, GameSession $session)
    {
        abort_unless((int) $session->user_id === (int) $request->user()->id, 403);
        abort_unless($session->level?->is_published, 404, 'This level is not part of the current release.');
        $data = $request->validate([
            'action' => 'required|in:revive,double_score,double_coins,extra_moves,hint,booster',
            'claim_token' => 'required|uuid',
        ]);
        $action = $data['action'];

        // Development-only mock ad. Production must use a provider-signed callback.
        return DB::transaction(function () use ($request, $session, $data, $action) {
            $game = GameSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $existing = RewardClaim::where('claim_token', $data['claim_token'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless((int) $existing->user_id === (int) $request->user()->id && (int) $existing->game_session_id === (int) $game->id, 403, 'Claim token mismatch.');
                return response()->json(['ok' => false, 'message' => 'This reward claim was already processed.'], 409);
            }

            if ($action === 'revive') {
                abort_unless($game->status === 'failed', 409, 'Revive is only available after a failed game.');
                abort_if($game->revives_used >= 1, 422, 'Revive limit reached for this level.');
                $state = $game->state ?? [];
                $checkpoint = $state['checkpoint'] ?? null;
                abort_unless($checkpoint && isset($checkpoint['worm'], $checkpoint['apples']), 422, 'No valid checkpoint is available.');
                $state['worm'] = $checkpoint['worm'];
                $state['apples'] = $checkpoint['apples'];
                $state['goldenApples'] = $checkpoint['goldenApples'] ?? ($state['goldenApples'] ?? []);
                $state['goldenApplesCollected'] = (int) ($checkpoint['goldenApplesCollected'] ?? ($state['goldenApplesCollected'] ?? 0));
                $state['moves'] = (int) $checkpoint['moves'];
                $state['score'] = (int) $checkpoint['score'];
                $state['lost'] = false;
                $game->update(['status' => 'active', 'state' => $state, 'score' => $state['score'], 'moves_used' => $state['moves'], 'revives_used' => $game->revives_used + 1, 'finished_at' => null]);
            } else {
                abort_unless($game->status === 'active', 409, 'This reward is only available during an active game.');
                $state = $game->state ?? [];
                if ($action === 'double_score') {
                    abort_if(!empty($state['double_score_claimed']), 422, 'Double score has already been used.');
                    $state['score'] = min(100000, (int) ($state['score'] ?? 0) * 2);
                    $state['double_score_claimed'] = true;
                    $game->update(['score' => $state['score'], 'state' => $state]);
                } elseif ($action === 'double_coins') {
                    abort_if(!empty($state['double_coins_claimed']), 422, 'Double coins has already been used.');
                    $state['double_coins_claimed'] = true;
                    $game->update(['state' => $state]);
                } elseif ($action === 'extra_moves') {
                    abort_if(!empty($state['extra_moves_claimed']), 422, 'Extra moves has already been used.');
                    $state['bonus_moves'] = (int) ($state['bonus_moves'] ?? 0) + 5;
                    $state['extra_moves_claimed'] = true;
                    $game->update(['state' => $state]);
                } elseif ($action === 'booster') {
                    abort_if(!empty($state['booster_claimed']), 422, 'Booster has already been used.');
                    $state['booster_active'] = true;
                    $state['booster_claimed'] = true;
                    $game->update(['state' => $state]);
                    $game->increment('boosters_used');
                } elseif ($action === 'hint') {
                    $game->increment('hints_used');
                }
            }

            $claim = RewardClaim::create([
                'user_id' => $request->user()->id,
                'game_session_id' => $game->id,
                'provider' => 'development_mock',
                'action' => $action,
                'claim_token' => $data['claim_token'],
                'status' => 'granted',
                'reward_payload' => ['simulated' => true],
                'granted_at' => now(),
            ]);

            $newAchievements = app(AchievementService::class)->recordCounter($request->user(), 'rewards');

            return response()->json([
                'ok' => true, 'action' => $action, 'simulated' => true,
                'achievements_unlocked' => $newAchievements,
                'state' => $game->fresh()->state,
                'status' => $game->fresh()->status,
                'message' => 'Development mock reward granted. No real advertisement was verified.',
            ]);
        });
    }
}
