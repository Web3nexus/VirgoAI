<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('workflow_task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('ai_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('ai_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('capability');
            $table->string('status')->default('pending');
            $table->string('idempotency_key')->nullable();
            $table->decimal('cost_usd', 12, 6)->nullable();
            $table->jsonb('usage')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
            $table->index('status');
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_records');
    }
};
