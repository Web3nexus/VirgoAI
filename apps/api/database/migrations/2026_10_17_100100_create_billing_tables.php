<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ulid('generation_id')->nullable();
            $table->string('type', 16);
            // Signed: credits are positive, charges negative. Micro-dollars.
            $table->bigInteger('amount_micros');
            $table->bigInteger('balance_after_micros');
            $table->string('note', 255)->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('generation_id');
        });

        Schema::create('model_prices', function (Blueprint $table) {
            $table->id();
            $table->string('provider_key', 64);
            $table->string('model_key', 128);
            // What one unit costs us, in micro-dollars. The unit names the billing quantity.
            $table->string('unit', 32);
            $table->bigInteger('cost_micros');
            $table->string('source', 32);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_key', 'model_key', 'unit']);
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('model_prices');
        Schema::dropIfExists('ledger_entries');
    }
};
