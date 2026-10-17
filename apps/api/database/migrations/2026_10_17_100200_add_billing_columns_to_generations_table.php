<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            // What the user is charged for this run (net of refunds), and what the provider costs us.
            $table->bigInteger('price_micros')->default(0);
            $table->bigInteger('cost_micros')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropColumn(['price_micros', 'cost_micros']);
        });
    }
};
