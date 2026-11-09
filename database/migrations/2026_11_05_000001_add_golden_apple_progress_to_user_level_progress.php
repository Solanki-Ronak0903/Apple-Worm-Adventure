<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_level_progress', function (Blueprint $table) {
            $table->unsignedTinyInteger('golden_apples_collected')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('user_level_progress', function (Blueprint $table) {
            $table->dropColumn('golden_apples_collected');
        });
    }
};
