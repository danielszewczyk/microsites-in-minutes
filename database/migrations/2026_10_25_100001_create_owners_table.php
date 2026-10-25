<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The account a microsite belongs to. Identified by email (passwordless in
     * the MVP: ownership of a site is proven by the manage token, below). The
     * plan and the Stripe columns are the monetization data model; nothing here
     * charges money.
     */
    public function up(): void
    {
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->string('plan')->default('free');
            $table->string('plan_status')->default('active');
            $table->string('billing_mode')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};
