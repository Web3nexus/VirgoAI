<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_secrets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            // Encrypted with APP_KEY by the model's cast. Never returned by the API.
            $table->text('value');
            $table->string('last4', 8);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_secrets');
    }
};
