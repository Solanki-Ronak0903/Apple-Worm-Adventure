<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserLevelProgress;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class AchievementService
{
    /**
     * Record a verified event (for example, an apple collected on the server).
     * Progress is stored per player and each achievement unlocks only once.
     */
    public function recordCounter(User $user, string $metric, int $amount = 1): array
    {
        if ($amount < 1) {
            return [];
        }

        $unlocked = [];

        foreach (Achievement::query()->orderBy('id')->get() as $achievement) {
            $criteria = (array) ($achievement->criteria ?? []);
            if (!array_key_exists($metric, $criteria)) {
                continue;
            }

            $record = UserAchievement::query()->firstOrCreate(
                ['user_id' => $user->id, 'achievement_id' => $achievement->id],
                ['progress' => []]
            );

            if ($record->unlocked_at) {
                continue;
            }

            $progress = (array) ($record->progress ?? []);
            $progress[$metric] = (int) ($progress[$metric] ?? 0) + $amount;
            $record->progress = $progress;

            if ($progress[$metric] >= (int) $criteria[$metric]) {
                $unlocked[] = $this->unlock($user, $achievement, $record);
            } else {
                $record->save();
            }
        }

        return $unlocked;
    }

    /** Recalculate achievements whose requirements come from saved progress. */
    public function syncCompletion(User $user): array
    {
        $completedLevels = UserLevelProgress::query()
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->whereHas('level', fn ($query) => $query->where('is_published', true))
            ->count();

        $publishedProgress = UserLevelProgress::query()
            ->where('user_id', $user->id)
            ->whereHas('level', fn ($query) => $query->where('is_published', true));

        $stars = (int) (clone $publishedProgress)->sum('stars');
        $goldenApples = (int) (clone $publishedProgress)
            ->where('completed', true)
            ->sum('golden_apples_collected');

        $unlocked = [];

        foreach (Achievement::query()->orderBy('id')->get() as $achievement) {
            $criteria = (array) ($achievement->criteria ?? []);
            $metric = null;
            $current = 0;

            if (array_key_exists('levels', $criteria)) {
                $metric = 'levels';
                $current = $completedLevels;
            } elseif (array_key_exists('stars', $criteria)) {
                $metric = 'stars';
                $current = $stars;
            } elseif (array_key_exists('golden_apples', $criteria)) {
                $metric = 'golden_apples';
                $current = $goldenApples;
            }

            if ($metric === null) {
                continue;
            }

            $record = UserAchievement::query()->firstOrCreate(
                ['user_id' => $user->id, 'achievement_id' => $achievement->id],
                ['progress' => []]
            );

            if ($record->unlocked_at) {
                continue;
            }

            $progress = (array) ($record->progress ?? []);
            $progress[$metric] = $current;
            $record->progress = $progress;

            if ($current >= (int) $criteria[$metric]) {
                $unlocked[] = $this->unlock($user, $achievement, $record);
            } else {
                $record->save();
            }
        }

        return $unlocked;
    }

    private function unlock(User $user, Achievement $achievement, UserAchievement $record): array
    {
        $record->unlocked_at = now();
        $record->save();

        $configuredReward = max(0, (int) (((array) $achievement->criteria)['reward_coins'] ?? 0));
        $reference = 'achievement-' . $user->id . '-' . $achievement->id;
        $coinsAwarded = 0;

        // The stable reference prevents farming achievement coins after a progress reset.
        if ($configuredReward > 0 && ! WalletTransaction::query()->where('reference', $reference)->exists()) {
            $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $wallet) {
                $wallet = Wallet::query()->create(['user_id' => $user->id, 'coins' => 100]);
            }

            $wallet->increment('coins', $configuredReward);
            $wallet->refresh();

            WalletTransaction::query()->create([
                'user_id' => $user->id,
                'type' => 'achievement_reward',
                'amount' => $configuredReward,
                'balance_after' => $wallet->coins,
                'reference' => $reference,
                'description' => 'Achievement unlocked: ' . $achievement->name,
                'metadata' => ['achievement_code' => $achievement->code],
            ]);
            $coinsAwarded = $configuredReward;
        }

        return [
            'id' => $achievement->id,
            'code' => $achievement->code,
            'name' => $achievement->name,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'coins_awarded' => $coinsAwarded,
        ];
    }
}
