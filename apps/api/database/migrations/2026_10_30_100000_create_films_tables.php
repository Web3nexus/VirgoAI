<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A long film: a story bible and a board of references, then takes of 4 to 15 seconds, grouped into chapters of
        // up to ten minutes. Chapters are joined into one file at the end.
        Schema::create('films', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ulid('generation_id')->nullable();
            $table->string('title', 200)->nullable();
            $table->text('brief');
            $table->unsignedInteger('length_seconds');
            $table->string('format', 32);
            $table->string('quality', 8);
            $table->json('styles')->nullable();
            // planning, board, rendering, assembling, succeeded, failed.
            $table->string('status', 16)->default('queued');
            $table->text('error')->nullable();
            // The story bible, the cast and locations, and each chapter's summary and memory.
            $table->jsonb('plan')->nullable();
            $table->string('output_path', 512)->nullable();
            // What the planning calls to the language model cost, recorded when each call settles.
            $table->bigInteger('planning_price')->default(0);
            $table->bigInteger('planning_cost')->default(0);
            // Set when the film's files are removed. The record stays, so its cost is still on the books.
            $table->timestamp('media_deleted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('film_assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('film_id');
            $table->foreign('film_id')->references('id')->on('films')->cascadeOnDelete();
            // cast or location.
            $table->string('kind', 16);
            // A short key the plan uses to refer to the asset, such as "mia" or "kitchen".
            $table->string('key', 64);
            $table->string('name', 200);
            $table->text('prompt');
            // pending, rendering, ready, failed.
            $table->string('status', 16)->default('pending');
            $table->jsonb('job')->nullable();
            $table->text('image_url')->nullable();
            $table->string('image_path', 512)->nullable();
            $table->bigInteger('price')->default(0);
            $table->bigInteger('cost')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['film_id', 'kind', 'key']);
        });

        Schema::create('film_takes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('film_id');
            $table->foreign('film_id')->references('id')->on('films')->cascadeOnDelete();
            $table->unsignedSmallInteger('chapter');
            // The take's place in the whole film, from 1.
            $table->unsignedInteger('position');
            $table->unsignedTinyInteger('seconds');
            $table->text('prompt');
            // The asset keys whose images are sent as references, in order. The prompt refers to them as @Image1, @Image2...
            $table->jsonb('refs');
            // pending, rendering, done, failed.
            $table->string('status', 16)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->jsonb('job')->nullable();
            $table->text('clip_url')->nullable();
            $table->string('clip_path', 512)->nullable();
            $table->string('normalized_path', 512)->nullable();
            $table->bigInteger('price')->default(0);
            $table->bigInteger('cost')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['film_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('film_takes');
        Schema::dropIfExists('film_assets');
        Schema::dropIfExists('films');
    }
};
