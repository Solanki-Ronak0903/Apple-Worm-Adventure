<?php

use App\Game\SideViewLevelCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep a narrow rollback snapshot so existing player progress, reward
     * records and administrator metadata are not touched by this campaign tune.
     */
    public function up(): void
    {
        Schema::create('side_view_level_rebalance_backups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('level_id')->unique();
            $table->json('original_values');
            $table->timestamps();
        });

        $catalog = SideViewLevelCatalog::all();
        $geometryFields = [
            'ground', 'platforms', 'walls', 'terrain', 'apples', 'goldenApples',
            'portal', 'blocks', 'spikes', 'switches', 'gates', 'start',
            'width', 'height', 'world', 'parMoves', 'moveLimit',
        ];

        $metadataDefaults = [
            17 => [
                'title' => 'Shard Passage',
                'lesson' => 'Two shard clusters guard the lower floor. Thread the upper passage.',
                'hint' => 'Stay above both thorn clusters until the far side.',
            ],
            21 => [
                'title' => 'Cloudstep',
                'lesson' => 'Two gaps need two growth boosts. Keep the worm connected across both.',
                'hint' => 'Each apple increases your reach. Check the tail before crossing the second gap.',
            ],
        ];

        foreach ($catalog as $number => $freshLayout) {
            $record = DB::table('levels')->where('number', $number)->first();
            if (! $record) {
                // Fresh installs are seeded from the updated catalog after migrations run.
                continue;
            }

            $config = is_array($record->config)
                ? $record->config
                : (json_decode((string) $record->config, true) ?: []);
            $sideView = $config['sideView'] ?? null;
            if (! is_array($sideView)) {
                continue;
            }

            $original = ['sideView' => [], 'missing_side_view_keys' => []];
            $changed = false;

            foreach ($geometryFields as $field) {
                if (! array_key_exists($field, $freshLayout)) {
                    continue;
                }

                $oldValueExists = array_key_exists($field, $sideView);
                $oldValue = $sideView[$field] ?? null;
                $newValue = $freshLayout[$field];
                if ($oldValueExists && $oldValue === $newValue) {
                    continue;
                }

                $original['sideView'][$field] = $oldValue;
                if (! $oldValueExists) {
                    $original['missing_side_view_keys'][] = $field;
                }
                $sideView[$field] = $newValue;
                $changed = true;
            }

            // Update the changed puzzle's explanatory text only when it still
            // matches the shipped defaults. Custom administrator wording survives.
            if (isset($metadataDefaults[$number])) {
                $defaults = $metadataDefaults[$number];
                foreach (['title', 'lesson', 'hint'] as $field) {
                    if (($sideView[$field] ?? null) === $defaults[$field]
                        && array_key_exists($field, $freshLayout)
                        && $freshLayout[$field] !== $sideView[$field]) {
                        $original['sideView'][$field] = $sideView[$field];
                        $sideView[$field] = $freshLayout[$field];
                        $changed = true;
                    }
                }

                if (($sideView['hint'] ?? null) === ($freshLayout['hint'] ?? null)
                    && isset($freshLayout['hintStages'])
                    && ($sideView['hintStages'] ?? null) !== $freshLayout['hintStages']) {
                    $original['sideView']['hintStages'] = $sideView['hintStages'] ?? null;
                    if (! array_key_exists('hintStages', $sideView)) {
                        $original['missing_side_view_keys'][] = 'hintStages';
                    }
                    $sideView['hintStages'] = $freshLayout['hintStages'];
                    $changed = true;
                }

                if ($record->title === $defaults['title'] && $freshLayout['title'] !== $record->title) {
                    $original['level_title'] = $record->title;
                    DB::table('levels')->where('id', $record->id)->update(['title' => $freshLayout['title']]);
                    $changed = true;
                }

                // Keep the legacy config title in sync for compatibility
                // screens that still read config.title instead of levels.title.
                $legacyTitle = sprintf('Level %02d · %s', (int) $number, $defaults['title']);
                if (($config['title'] ?? null) === $legacyTitle) {
                    $original['config_title'] = $config['title'];
                    $config['title'] = sprintf('Level %02d · %s', (int) $number, $freshLayout['title']);
                    $changed = true;
                }
            }

            if (! $changed) {
                continue;
            }

            DB::table('side_view_level_rebalance_backups')->insertOrIgnore([
                'level_id' => $record->id,
                'original_values' => json_encode($original, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $config['sideView'] = $sideView;
            DB::table('levels')->where('id', $record->id)->update([
                'config' => json_encode($config, JSON_THROW_ON_ERROR),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('side_view_level_rebalance_backups')) {
            return;
        }

        foreach (DB::table('side_view_level_rebalance_backups')->get() as $backup) {
            $record = DB::table('levels')->where('id', $backup->level_id)->first();
            if (! $record) {
                continue;
            }

            $original = json_decode((string) $backup->original_values, true) ?: [];
            $config = is_array($record->config)
                ? $record->config
                : (json_decode((string) $record->config, true) ?: []);
            $sideView = $config['sideView'] ?? [];
            $missing = array_flip($original['missing_side_view_keys'] ?? []);

            foreach (($original['sideView'] ?? []) as $field => $value) {
                if (isset($missing[$field])) {
                    unset($sideView[$field]);
                } else {
                    $sideView[$field] = $value;
                }
            }

            $config['sideView'] = $sideView;
            $updates = ['config' => json_encode($config, JSON_THROW_ON_ERROR)];
            if (array_key_exists('config_title', $original)) {
                $config['title'] = $original['config_title'];
                $updates['config'] = json_encode($config, JSON_THROW_ON_ERROR);
            }
            if (array_key_exists('level_title', $original)) {
                $updates['title'] = $original['level_title'];
            }
            DB::table('levels')->where('id', $record->id)->update($updates);
        }

        Schema::dropIfExists('side_view_level_rebalance_backups');
    }
};
