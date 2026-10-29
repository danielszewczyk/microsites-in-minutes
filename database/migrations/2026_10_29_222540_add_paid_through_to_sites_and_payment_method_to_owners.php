<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->timestamp('paid_through')->nullable()->after('published_at');
        });

        Schema::table('owners', function (Blueprint $table) {
            $table->string('stripe_payment_method_id')->nullable()->after('stripe_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('paid_through');
        });

        Schema::table('owners', function (Blueprint $table) {
            $table->dropColumn('stripe_payment_method_id');
        });
    }
};
