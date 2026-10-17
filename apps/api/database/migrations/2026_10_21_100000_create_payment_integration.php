<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every provider event we have accepted. The unique key makes a repeated delivery a no-op.
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 191);
            $table->string('type', 64);
            $table->string('outcome', 32);
            $table->string('purchase_id', 26)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['provider', 'event_id']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedBigInteger('flutterwave_monthly_plan_id')->nullable();
            $table->unsignedBigInteger('flutterwave_yearly_plan_id')->nullable();
            $table->string('paddle_monthly_price_id', 64)->nullable();
            $table->string('paddle_yearly_price_id', 64)->nullable();
        });

        Schema::table('credit_packs', function (Blueprint $table) {
            $table->string('paddle_price_id', 64)->nullable();
            $table->string('paddle_subscriber_price_id', 64)->nullable();
        });

        Schema::table('purchases', function (Blueprint $table) {
            // The hosted checkout link. Stored once, so a repeated request returns the same link.
            $table->text('checkout_url')->nullable();
            $table->string('provider_subscription_id', 128)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['checkout_url', 'provider_subscription_id']);
        });
        Schema::table('credit_packs', function (Blueprint $table) {
            $table->dropColumn(['paddle_price_id', 'paddle_subscriber_price_id']);
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['flutterwave_monthly_plan_id', 'flutterwave_yearly_plan_id', 'paddle_monthly_price_id', 'paddle_yearly_price_id']);
        });
        Schema::dropIfExists('payment_events');
    }
};
