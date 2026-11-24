<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Defensive recovery: if migration 000001 was marked as completed in
        // the migrations table but its table is absent, create it here too.
        // This specifically prevents the missing-table failure from the prior patch.
        if (! Schema::hasTable('user_level_rewards')) {
            Schema::create('user_level_rewards', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('level_id')->constrained()->cascadeOnDelete();
                $table->string('reward_code', 60);
                $table->unsignedSmallInteger('quantity')->default(0);
                $table->unsignedInteger('coins_awarded')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamp('granted_at')->nullable();
                $table->timestamps();
                $table->unique(
                    ['user_id', 'level_id', 'reward_code'],
                    'user_level_reward_unique'
                );
            });
        }

        if (! Schema::hasTable('user_level_progress') || ! Schema::hasTable('levels')) {
            return;
        }

        // Backfill a permanent first-clear marker for previously completed levels.
        DB::table('user_level_progress')
            ->where('completed', true)
            ->whereIn(
                'level_id',
                DB::table('levels')->where('is_published', true)->select('id')
            )
            ->orderBy('id')
            ->chunkById(200, function ($progressRows): void {
                foreach ($progressRows as $progress) {
                    $sessionIds = DB::table('game_sessions')
                        ->where('user_id', $progress->user_id)
                        ->where('level_id', $progress->level_id)
                        ->where('status', 'completed')
                        ->pluck('id');

                    $references = $sessionIds
                        ->map(fn ($sessionId) => 'level-' . $sessionId)
                        ->all();

                    $historicalCoins = $references === []
                        ? 0
                        : (int) DB::table('wallet_transactions')
                            ->whereIn('reference', $references)
                            ->sum('amount');

                    $now = now();

                    DB::table('user_level_rewards')->insertOrIgnore([
                        'user_id' => $progress->user_id,
                        'level_id' => $progress->level_id,
                        'reward_code' => 'level_completion',
                        'quantity' => 1,
                        'coins_awarded' => max(0, $historicalCoins),
                        'metadata' => json_encode([
                            'backfilled' => true,
                            'completed_sessions' => $sessionIds->count(),
                            'historical_coin_transactions' => count($references),
                        ]),
                        'granted_at' => $progress->completed_at ?? $progress->updated_at ?? $now,
                        'created_at' => $progress->created_at ?? $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Deliberately retain first-clear markers to prevent duplicate payouts.
    }
};
