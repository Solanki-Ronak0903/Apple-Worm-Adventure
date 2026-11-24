<?php

use App\Game\SideViewLevelCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Expand Worlds 2–5 with authored detour collectibles and selected hazards.
     * Existing player progress, wallets, reward claims and achievements are not
     * modified. The migration updates only the side-view level definitions.
     */
    public function up(): void
    {
        $catalog = SideViewLevelCatalog::all();

        foreach (range(6, 25) as $number) {
            $row = DB::table('levels')->where('number', $number)->first();
            $layout = $catalog[$number] ?? null;
            if (! $row || ! is_array($layout)) {
                continue;
            }

            $config = is_array($row->config)
                ? $row->config
                : (json_decode((string) $row->config, true) ?: []);

            $config['sideView'] = $layout;
            $config['world'] = $layout['world'];
            $config['boardMode'] = 'side_view_puzzle';
            $config['title'] = sprintf('Level %02d · %s', $number, $layout['title']);
            $config['layout'] = array_merge($config['layout'] ?? [], [
                'pattern' => 'hand_authored_side_view',
                'label' => $layout['lesson'],
            ]);

            DB::table('levels')
                ->where('id', $row->id)
                ->update([
                    'title' => $config['title'],
                    'difficulty' => $layout['difficulty'],
                    'config' => json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Content-only migration: do not restore old maps after players may
        // have started the new puzzles or recorded progress against them.
    }
};
