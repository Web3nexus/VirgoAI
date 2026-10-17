<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            // The provider's job handle (queue URLs, endpoint, usage record) while a render runs.
            $table->jsonb('provider_job')->nullable()->after('cost_usd');
        });
    }

    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropColumn('provider_job');
        });
    }
};
