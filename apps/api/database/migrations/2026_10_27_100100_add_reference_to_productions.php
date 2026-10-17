<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A reference image the user attached. Every keyframe is edited from it, so the look stays the same.
        Schema::table('productions', function (Blueprint $table) {
            $table->string('reference_url', 2048)->nullable()->after('skill_key');
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->dropColumn('reference_url');
        });
    }
};
