<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the video or image file was deleted, by the user or by the retention rule. The row stays for records.
        Schema::table('productions', function (Blueprint $table) {
            $table->timestamp('media_deleted_at')->nullable()->index();
        });
        Schema::table('generations', function (Blueprint $table) {
            $table->timestamp('media_deleted_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('productions', fn (Blueprint $table) => $table->dropColumn('media_deleted_at'));
        Schema::table('generations', fn (Blueprint $table) => $table->dropColumn('media_deleted_at'));
    }
};
