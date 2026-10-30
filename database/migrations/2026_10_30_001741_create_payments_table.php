<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per credited Stripe payment. The reference (a Checkout session,
     * PaymentIntent or invoice id) is unique, so a redelivered or concurrent
     * webhook that tries to credit the same payment twice fails the insert and
     * is skipped — idempotency enforced by the database, not by app-side state.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
