<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clip_sources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // upload: a file we store and cut. youtube: a link we play in full, never downloaded.
            $table->string('kind', 16);
            // uploaded, probing, transcribing, ready, failed.
            $table->string('status', 16)->default('uploaded');
            $table->string('original_name', 255)->nullable();
            $table->string('youtube_id', 16)->nullable();
            $table->string('storage_path', 512)->nullable();
            $table->decimal('duration_seconds', 10, 3)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            // [{start, end, text}] in seconds from the start of the source.
            $table->jsonb('transcript')->nullable();
            $table->ulid('transcript_generation_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('clip_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ulid('clip_source_id');
            $table->foreign('clip_source_id')->references('id')->on('clip_sources')->cascadeOnDelete();
            $table->ulid('generation_id')->nullable();
            $table->text('request');
            $table->string('model_key', 128);
            $table->unsignedTinyInteger('count');
            $table->unsignedSmallInteger('min_seconds');
            $table->unsignedSmallInteger('max_seconds');
            // queued, running, succeeded, failed.
            $table->string('status', 16)->default('queued');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['clip_source_id', 'created_at']);
        });

        Schema::create('clips', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('clip_run_id');
            $table->foreign('clip_run_id')->references('id')->on('clip_runs')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->decimal('start_seconds', 10, 3);
            $table->decimal('end_seconds', 10, 3);
            $table->string('title', 160);
            $table->text('reason')->nullable();
            $table->string('storage_path', 512)->nullable();
            $table->timestamps();

            $table->unique(['clip_run_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clips');
        Schema::dropIfExists('clip_runs');
        Schema::dropIfExists('clip_sources');
    }
};
