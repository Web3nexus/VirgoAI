<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An original story written from a source video's transcript. The transcript is read, never shown or copied.
        Schema::create('remixes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ulid('clip_source_id');
            $table->foreign('clip_source_id')->references('id')->on('clip_sources')->cascadeOnDelete();
            $table->ulid('generation_id')->nullable();
            $table->string('model_key', 128);
            $table->string('status', 16)->default('queued');
            $table->text('instructions')->nullable();
            $table->string('title', 200)->nullable();
            $table->text('logline')->nullable();
            $table->longText('story')->nullable();
            // Shared 8-word runs with the source. Must be zero before the story is kept.
            $table->unsignedInteger('shared_runs')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remixes');
    }
};
