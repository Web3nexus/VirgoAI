<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A full video: a script, then a keyframe and a motion clip for each scene, joined into one file.
        Schema::create('productions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('prompt');
            $table->string('format', 32);
            $table->string('quality', 8);
            $table->json('styles')->nullable();
            $table->string('model_key', 128);
            // queued, keyframing, animating, assembling, succeeded, failed.
            $table->string('status', 16)->default('queued');
            $table->text('error')->nullable();
            $table->string('output_path', 512)->nullable();
            $table->ulid('generation_id')->nullable();
            $table->jsonb('script')->nullable();
            $table->bigInteger('script_price')->default(0);
            $table->bigInteger('script_cost')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('production_scenes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('production_id');
            $table->foreign('production_id')->references('id')->on('productions')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('title', 200);
            $table->text('image_prompt');
            $table->text('video_prompt');
            // pending, keyframing, keyframed, animating, done, failed.
            $table->string('status', 16)->default('pending');
            $table->jsonb('keyframe_job')->nullable();
            $table->text('keyframe_url')->nullable();
            $table->string('keyframe_path', 512)->nullable();
            $table->bigInteger('keyframe_price')->default(0);
            $table->bigInteger('keyframe_cost')->default(0);
            $table->jsonb('motion_job')->nullable();
            $table->string('clip_path', 512)->nullable();
            $table->bigInteger('motion_price')->default(0);
            $table->bigInteger('motion_cost')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['production_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_scenes');
        Schema::dropIfExists('productions');
    }
};
