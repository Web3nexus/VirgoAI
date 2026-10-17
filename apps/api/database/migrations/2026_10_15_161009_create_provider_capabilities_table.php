<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('ai_model_id')->constrained()->cascadeOnDelete();
            $table->string('capability');
            $table->jsonb('constraints')->nullable();
            $table->timestamps();

            $table->unique(['ai_model_id', 'capability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_capabilities');
    }
};
