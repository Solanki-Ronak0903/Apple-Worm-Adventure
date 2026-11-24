<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public const STARTING_COINS = 100;

    /**
     * Return the player's wallet and guarantee one immutable opening-balance
     * ledger entry exists for it. The operation is safe to call repeatedly.
     */
    public function getOrCreate(User $user): Wallet
    {
        return DB::transaction(function () use ($user): Wallet {
            // Serialize wallet creation/opening-ledger creation per player.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = Wallet::query()->create([
                    'user_id' => $user->id,
                    'coins' => self::STARTING_COINS,
                ]);
            }

            $reference = $this->openingReference((int) $user->id);
            $openingEntry = WalletTransaction::query()->firstOrNew([
                'reference' => $reference,
            ]);

            if (! $openingEntry->exists) {
                $openingEntry->fill([
                    'user_id' => $user->id,
                    'type' => 'opening_balance',
                    'amount' => self::STARTING_COINS,
                    'balance_after' => self::STARTING_COINS,
                    'description' => 'Starting player coin balance',
                    'metadata' => ['source' => 'account_opening'],
                ]);
                $openingEntry->created_at = $wallet->created_at ?? now();
                $openingEntry->updated_at = $wallet->created_at ?? now();
                $openingEntry->save();
            }

            return $wallet->refresh();
        });
    }

    public function openingReference(int $userId): string
    {
        return 'wallet-opening-' . $userId;
    }
}
