<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('user')->index();
            $table->timestamp('suspended_at')->nullable();
            // Prepaid wallet in micro-dollars (1 USD = 1,000,000). Integers avoid float rounding.
            $table->bigInteger('balance_micros')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'suspended_at', 'balance_micros']);
        });
    }
};
