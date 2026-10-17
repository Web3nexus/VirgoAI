<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name', 64);
            $table->string('tagline', 160)->nullable();
            // Micro-dollars. A yearly price is the total paid up front; the credit still arrives monthly.
            $table->bigInteger('monthly_micros');
            $table->bigInteger('yearly_micros');
            // Credit added to the wallet each month. Spent before top-up credit, and it expires.
            $table->bigInteger('monthly_credit_micros');
            $table->json('features');
            $table->boolean('highlighted')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('credit_packs', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('name', 64);
            // Credit received, and the list price. Subscribers pay the discounted price.
            $table->bigInteger('credits_micros');
            $table->bigInteger('price_micros');
            $table->unsignedTinyInteger('subscriber_discount_percent')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan_key', 32);
            $table->string('interval', 16);
            // active, past_due (period over, inside the grace window), ended, or canceled.
            $table->string('status', 16);
            $table->timestamp('current_period_end');
            $table->timestamp('next_credit_at');
            $table->boolean('cancel_at_period_end')->default(false);
            $table->string('provider', 32)->default('manual');
            $table->string('provider_ref', 128)->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // subscription (new plan), subscription_renewal, or credit_pack.
            $table->string('kind', 32);
            $table->string('item_key', 32);
            $table->string('interval', 16)->nullable();
            $table->bigInteger('amount_micros');
            $table->bigInteger('credits_micros');
            // pending, paid, failed. Only a paid purchase grants anything.
            $table->string('status', 16)->default('pending');
            $table->string('provider', 32)->default('manual');
            $table->string('provider_ref', 128)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            // The part of balance_micros that came from a subscription and expires at the end of its cycle.
            $table->bigInteger('subscription_credit_micros')->default(0);
        });

        $now = now();
        DB::table('plans')->insert([
            [
                'key' => 'starter', 'name' => 'Starter', 'tagline' => 'Try the studio and make your first videos.',
                'monthly_micros' => 19_000_000, 'yearly_micros' => 190_000_000, 'monthly_credit_micros' => 19_000_000,
                'features' => json_encode(['Monthly credit for images, video and scripts', 'Every model in the studio', 'Email support']),
                'highlighted' => false, 'active' => true, 'sort' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'key' => 'pro', 'name' => 'Pro', 'tagline' => 'For creators who publish every week.',
                'monthly_micros' => 49_000_000, 'yearly_micros' => 470_000_000, 'monthly_credit_micros' => 49_000_000,
                'features' => json_encode(['Larger monthly credit', 'Subscriber pricing on top-ups', 'Priority rendering', 'Email support']),
                'highlighted' => true, 'active' => true, 'sort' => 2, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'key' => 'studio', 'name' => 'Studio', 'tagline' => 'For teams producing at volume.',
                'monthly_micros' => 149_000_000, 'yearly_micros' => 1_430_000_000, 'monthly_credit_micros' => 149_000_000,
                'features' => json_encode(['Largest monthly credit', 'Best subscriber pricing on top-ups', 'Priority rendering', 'Priority support']),
                'highlighted' => false, 'active' => true, 'sort' => 3, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        DB::table('credit_packs')->insert([
            ['key' => 'topup_25', 'name' => '$25 top-up', 'credits_micros' => 25_000_000, 'price_micros' => 25_000_000, 'subscriber_discount_percent' => 10, 'active' => true, 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'topup_100', 'name' => '$100 top-up', 'credits_micros' => 100_000_000, 'price_micros' => 100_000_000, 'subscriber_discount_percent' => 15, 'active' => true, 'sort' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('subscription_credit_micros');
        });
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('credit_packs');
        Schema::dropIfExists('plans');
    }
};
