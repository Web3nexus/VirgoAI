<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('approval_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision');
            $table->text('notes')->nullable();
            $table->jsonb('revision')->nullable();
            $table->timestamps();

            $table->index('approval_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
    }
};
