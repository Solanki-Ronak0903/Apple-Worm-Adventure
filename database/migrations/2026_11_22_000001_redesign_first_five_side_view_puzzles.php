<?php

use App\Game\SideViewLevelCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replace only the active side-view maps for the first world.
     * Player progress, rewards, publication status and legacy compatibility
     * data remain untouched.
     */
    public function up(): void
    {
        $catalog = SideViewLevelCatalog::all();

        foreach (range(1, 5) as $number) {
            $row = DB::table('levels')->where('number', $number)->first();
            if (! $row) {
                continue;
            }

            $config = is_array($row->config)
                ? $row->config
                : (json_decode((string) $row->config, true) ?: []);

            if (! isset($catalog[$number])) {
                continue;
            }

            $sideView = $catalog[$number];
            $config['sideView'] = $sideView;
            $config['world'] = $sideView['world'];
            $config['title'] = sprintf('Level %02d · %s', $number, $sideView['title']);
            $config['boardMode'] = 'side_view_puzzle';
            $config['layout'] = array_merge($config['layout'] ?? [], [
                'pattern' => 'hand_authored_side_view',
                'label' => $sideView['lesson'],
            ]);

            DB::table('levels')
                ->where('id', $row->id)
                ->update([
                    'title' => $config['title'],
                    'difficulty' => $sideView['difficulty'],
                    'config' => json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Content-only migration. Deliberately do not roll player-facing maps
        // back automatically after players may have begun the redesigned levels.
    }
};
