<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve future levels and player progress; only hide them from the
        // first public release until they have been reviewed and published.
        DB::table('levels')
            ->where('number', '>', 25)
            ->update([
                'is_published' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Deliberately do not republish future levels automatically. Their
        // publication status should be reviewed before a later release.
    }
};
