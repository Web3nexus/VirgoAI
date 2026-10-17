<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Output format decides the video dimensions. See Project::FORMATS.
            $table->string('format', 32)->default('landscape_16_9')->after('type');
            // Public URL of the rendered preview video, set once a render exists.
            $table->string('preview_url', 2048)->nullable()->after('format');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['format', 'preview_url']);
        });
    }
};
