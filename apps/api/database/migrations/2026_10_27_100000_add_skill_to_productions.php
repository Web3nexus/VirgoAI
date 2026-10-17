<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The skill the user selected. It shapes the script the full video is built from.
        Schema::table('productions', function (Blueprint $table) {
            $table->string('skill_key', 64)->nullable()->after('styles');
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->dropColumn('skill_key');
        });
    }
};
