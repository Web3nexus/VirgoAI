<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A long video split into short parts can have hundreds of clips. A tinyint counter would overflow.
        Schema::table('clip_runs', function (Blueprint $table) {
            $table->unsignedSmallInteger('count')->change();
        });
        Schema::table('clips', function (Blueprint $table) {
            $table->unsignedSmallInteger('position')->change();
        });
    }

    public function down(): void
    {
        Schema::table('clip_runs', function (Blueprint $table) {
            $table->unsignedTinyInteger('count')->change();
        });
        Schema::table('clips', function (Blueprint $table) {
            $table->unsignedTinyInteger('position')->change();
        });
    }
};
