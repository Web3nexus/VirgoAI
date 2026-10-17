<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ulid('project_id')->nullable();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->string('tool', 32);
            $table->string('capability', 32);
            $table->string('provider_key', 64)->nullable();
            $table->string('model_key', 128)->nullable();
            $table->text('prompt');
            $table->jsonb('options')->nullable();
            $table->string('status', 16);
            $table->string('output_type', 16)->nullable();
            $table->text('output_text')->nullable();
            $table->string('output_path', 512)->nullable();
            $table->text('error')->nullable();
            $table->decimal('cost_usd', 10, 4)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tool', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
