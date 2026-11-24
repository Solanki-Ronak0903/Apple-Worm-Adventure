<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The original patch archive accidentally omitted this table-creation
        // migration. Guard the create so recovery is also safe when a previous
        // partial/manual repair has already created the table.
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

        // Preserve historical Golden Apple completions before progress can be reset.
        if (Schema::hasTable('user_level_progress')
            && Schema::hasColumn('user_level_progress', 'golden_apples_collected')
            && Schema::hasTable('levels')) {
            DB::table('user_level_progress')
                ->where('completed', true)
                ->where('golden_apples_collected', '>', 0)
                ->whereIn(
                    'level_id',
                    DB::table('levels')->where('is_published', true)->select('id')
                )
                ->orderBy('id')
                ->chunkById(200, function ($progressRows): void {
                    foreach ($progressRows as $progress) {
                        $quantity = (int) $progress->golden_apples_collected;
                        $now = now();

                        DB::table('user_level_rewards')->insertOrIgnore([
                            'user_id' => $progress->user_id,
                            'level_id' => $progress->level_id,
                            'reward_code' => 'golden_apple_completion',
                            'quantity' => $quantity,
                            'coins_awarded' => $quantity * 20,
                            'metadata' => json_encode(['backfilled' => true]),
                            'granted_at' => $progress->completed_at ?? $progress->updated_at ?? $now,
                            'created_at' => $progress->created_at ?? $now,
                            'updated_at' => $now,
                        ]);
                    }
                });
        }

        // Add a one-time opening-balance ledger row for existing wallets.
        // This records history; it does not change the current wallet balance.
        if (Schema::hasTable('wallets') && Schema::hasTable('wallet_transactions')) {
            DB::table('wallets')->orderBy('id')->chunkById(200, function ($wallets): void {
                foreach ($wallets as $wallet) {
                    $now = $wallet->created_at ?? now();

                    DB::table('wallet_transactions')->insertOrIgnore([
                        'user_id' => $wallet->user_id,
                        'type' => 'opening_balance',
                        'amount' => 100,
                        'balance_after' => 100,
                        'reference' => 'wallet-opening-' . $wallet->user_id,
                        'description' => 'Starting player coin balance',
                        'metadata' => json_encode([
                            'source' => 'account_opening',
                            'backfilled' => true,
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        // Keep the wallet-opening rows; removing reward markers could permit
        // previously paid rewards to be issued again after a rollback.
        Schema::dropIfExists('user_level_rewards');
    }
};
