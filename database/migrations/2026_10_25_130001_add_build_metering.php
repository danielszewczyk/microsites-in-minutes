<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Build metering: the product bills by the time it takes to build a site
     * ("microsites in minutes"). Every build — first publish or a regenerate —
     * records how long it took and what it cost; the cost accrues onto the
     * owner's running balance. Nothing is actually charged (the Stripe stub).
     */
    public function up(): void
    {
        Schema::create('build_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->unsignedInteger('duration_ms');
            $table->unsignedInteger('cost_cents');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('owners', function (Blueprint $table) {
            $table->unsignedBigInteger('build_balance_cents')->default(0);
            $table->unsignedBigInteger('build_ms_total')->default(0);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->unsignedInteger('last_build_ms')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('build_events');

        Schema::table('owners', function (Blueprint $table) {
            $table->dropColumn(['build_balance_cents', 'build_ms_total']);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('last_build_ms');
        });
    }
};
