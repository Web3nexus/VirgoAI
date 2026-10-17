<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('stage');
            $table->string('name');
            $table->string('status')->default('pending');
            $table->unsignedInteger('position')->default(0);
            $table->string('approval_policy')->default('not_required');
            $table->string('handler');
            $table->jsonb('input')->nullable();
            $table->jsonb('output')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_id', 'key']);
            $table->index(['workflow_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_tasks');
    }
};
