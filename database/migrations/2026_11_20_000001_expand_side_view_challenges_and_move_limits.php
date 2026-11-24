<?php

use App\Game\SideViewLevelCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a rune-and-gate objective to four existing stock maps and give later
     * worlds a little more room to experiment. Custom map geometry and custom
     * move limits are preserved; a backup makes the change reversible.
     */
    public function up(): void
    {
        Schema::create('side_view_challenge_expansion_backups', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('level_id')->unique();
            $table->json('original_values');
            $table->timestamps();
        });

        $catalog = SideViewLevelCatalog::all();
        $oldSlackByWorld = [1 => 5, 2 => 4, 3 => 3, 4 => 2, 5 => 2];
        $oldText = [
            8 => [
                'lesson' => 'Climb over the stone divider to reach the far ledge.',
                'hint' => 'The wall reaches the floor. Find the open cells above its top.',
                'hintStages' => [
                    'The stone divider reaches the ground and blocks the direct route.',
                    'Look for open cells above the wall rather than trying to push through it.',
                    'Use the space above the divider to cross to the far side, then collect the far apple and reach the portal.',
                ],
            ],
            12 => [
                'lesson' => 'Push the crate aside and route through the narrow upper pocket.',
                'hint' => 'A crate blocks the straight line. Move it before taking the upper lane.',
                'hintStages' => [
                    'The crate occupies the direct lane, while the upper pocket provides another route.',
                    'Move the crate out of the approach before trying to enter the narrow upper passage.',
                    'Clear the crate, collect the lower apple, then use the upper pocket for the second apple before reaching the exit.',
                ],
            ],
            19 => [
                'lesson' => 'Three rising shelves demand careful turns and room for the growing body.',
                'hint' => 'Take the steps in order. A longer worm needs room at each turn.',
                'hintStages' => [
                    'The three shelves form a staircase that rises from left to right.',
                    'Take the shelves in order; the growing worm needs room to turn at each step.',
                    'Collect the apples on the first, second and third shelves, then route back down to the portal on the ground.',
                ],
            ],
            23 => [
                'lesson' => 'A long thorn bed forces a precise route along the upper cloud shelf.',
                'hint' => 'Keep the body on the upper shelf until the far side of the spike bed.',
                'hintStages' => [
                    'The thorn bed is too long to cross safely at floor level.',
                    'A raised cloud shelf passes above the whole hazard row.',
                    'Take the upper shelf, collect both apples, and remain above the spikes until the far side is clear.',
                ],
            ],
        ];

        // The maps must still match their shipped geometry before this migration
        // adds objects. This avoids overwriting an administrator's custom map.
        $baselineFields = [
            'terrain', 'apples', 'goldenApples', 'portal', 'blocks', 'spikes',
            'start', 'width', 'height', 'parMoves', 'world',
        ];

        foreach ($catalog as $number => $fresh) {
            $record = DB::table('levels')->where('number', $number)->first();
            if (! $record) {
                continue;
            }

            $config = is_array($record->config)
                ? $record->config
                : (json_decode((string) $record->config, true) ?: []);
            $sideView = $config['sideView'] ?? null;
            if (! is_array($sideView)) {
                continue;
            }

            $matchesStockGeometry = true;
            foreach ($baselineFields as $field) {
                if (($sideView[$field] ?? null) !== ($fresh[$field] ?? null)) {
                    $matchesStockGeometry = false;
                    break;
                }
            }

            $original = ['sideView' => [], 'missing_side_view_keys' => []];
            $changed = false;

            // Update all untouched shipped move budgets, including the late
            // campaign levels visible in the supplied screenshots.
            $worldNumber = intdiv($number - 1, 5) + 1;
            $oldDefaultLimit = (int) ($fresh['parMoves'] ?? 0) + $oldSlackByWorld[$worldNumber];
            if ((int) ($sideView['moveLimit'] ?? 0) === $oldDefaultLimit
                && (int) ($sideView['moveLimit'] ?? 0) !== (int) $fresh['moveLimit']) {
                $original['sideView']['moveLimit'] = $sideView['moveLimit'];
                $sideView['moveLimit'] = $fresh['moveLimit'];
                $changed = true;
            }

            // Add new objectives only to stock copies of the four selected maps.
            $isChallengeExpansionLevel = in_array((int) $number, [8, 12, 19, 23], true);
            $hasNoCustomMechanisms = empty($sideView['switches'] ?? []) && empty($sideView['gates'] ?? []);
            if ($isChallengeExpansionLevel && $matchesStockGeometry && $hasNoCustomMechanisms) {
                foreach (['switches', 'gates'] as $field) {
                    $oldValueExists = array_key_exists($field, $sideView);
                    $oldValue = $sideView[$field] ?? null;
                    if ($oldValueExists && $oldValue === $fresh[$field]) {
                        continue;
                    }

                    $original['sideView'][$field] = $oldValue;
                    if (! $oldValueExists) {
                        $original['missing_side_view_keys'][] = $field;
                    }
                    $sideView[$field] = $fresh[$field];
                    $changed = true;
                }
            }

            // Refresh only the original shipped copy for these maps. Custom
            // administrator lesson text and clues remain untouched.
            if ($isChallengeExpansionLevel && $matchesStockGeometry && isset($oldText[$number])) {
                foreach (['lesson', 'hint', 'hintStages'] as $field) {
                    if (($sideView[$field] ?? null) === $oldText[$number][$field]
                        && ($fresh[$field] ?? null) !== $sideView[$field]) {
                        $original['sideView'][$field] = $sideView[$field];
                        $sideView[$field] = $fresh[$field];
                        $changed = true;
                    }
                }
            }

            if (! $changed) {
                continue;
            }

            DB::table('side_view_challenge_expansion_backups')->insert([
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
        if (! Schema::hasTable('side_view_challenge_expansion_backups')) {
            return;
        }

        foreach (DB::table('side_view_challenge_expansion_backups')->get() as $backup) {
            $record = DB::table('levels')->where('id', $backup->level_id)->first();
            if (! $record) {
                continue;
            }

            $config = is_array($record->config)
                ? $record->config
                : (json_decode((string) $record->config, true) ?: []);
            $sideView = $config['sideView'] ?? [];
            $original = json_decode((string) $backup->original_values, true) ?: [];
            $missing = array_flip($original['missing_side_view_keys'] ?? []);

            foreach (($original['sideView'] ?? []) as $field => $value) {
                if (isset($missing[$field])) {
                    unset($sideView[$field]);
                } else {
                    $sideView[$field] = $value;
                }
            }

            $config['sideView'] = $sideView;
            DB::table('levels')->where('id', $record->id)->update([
                'config' => json_encode($config, JSON_THROW_ON_ERROR),
            ]);
        }

        Schema::dropIfExists('side_view_challenge_expansion_backups');
    }
};
