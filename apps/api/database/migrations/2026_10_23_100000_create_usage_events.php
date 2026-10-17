<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per step of a production: transcription, rewrite, script, each clip analysis, each render.
        // A production's total is the sum of its steps. Users see tokens and credits. Cost and margin are backend-only.
        Schema::create('usage_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('production_type', 32);
            $table->string('production_id', 26);
            $table->string('step', 48);
            $table->string('provider_key', 64)->nullable();
            $table->string('model_key', 128)->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->decimal('units', 14, 3)->default(0);
            $table->string('unit_label', 24)->nullable();
            $table->bigInteger('cost_micros')->default(0);
            $table->bigInteger('price_micros')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['production_type', 'production_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
