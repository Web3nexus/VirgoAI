<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            // The image-to-video model the run's motion clips are made with. Null on older runs, which used Seedance 2.0.
            $table->string('video_model_key', 128)->nullable()->after('model_key');
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->dropColumn('video_model_key');
        });
    }
};
