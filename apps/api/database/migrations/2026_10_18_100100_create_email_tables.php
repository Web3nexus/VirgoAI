<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 120);
            $table->string('subject', 200);
            $table->text('body');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('template_key', 64)->nullable();
            $table->string('to_email', 255);
            $table->string('subject', 200);
            $table->string('status', 16);
            $table->text('error')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });

        $now = now();
        DB::table('email_templates')->insert([
            [
                'key' => 'welcome',
                'name' => 'Welcome',
                'subject' => 'Welcome to virgoAi',
                'body' => "Hi {{name}},\n\nYour virgoAi account is ready. Open the studio to start your first production.\n\n{{app}}",
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'credit_granted',
                'name' => 'Credit added',
                'subject' => 'Credit added to your account',
                'body' => "Hi {{name}},\n\n{{amount}} was added to your balance. Your balance is now {{balance}}.\n\n{{app}}",
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'account_suspended',
                'name' => 'Account suspended',
                'subject' => 'Your virgoAi account is suspended',
                'body' => "Hi {{name}},\n\nYour virgoAi account has been suspended. Contact {{support}} if you think this is a mistake.\n\n{{app}}",
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('email_templates');
    }
};
