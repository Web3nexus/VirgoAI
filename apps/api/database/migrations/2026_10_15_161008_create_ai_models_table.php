<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ai_provider_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('family')->nullable();
            $table->unsignedInteger('context_window')->nullable();
            $table->unsignedInteger('max_output')->nullable();
            $table->jsonb('pricing')->nullable();
            $table->jsonb('meta')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();

            $table->unique(['ai_provider_id', 'key']);
            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
