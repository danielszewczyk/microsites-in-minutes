<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-minute view/entry counts: the finest analytics tier, kept for a short
     * window (pruned to ~2 days by stats:prune) and rolled up into the hourly
     * and daily tiers. Like site_stats, counts are incremented forward-only as
     * hits happen, so there is no back-fill before the feature shipped.
     */
    public function up(): void
    {
        Schema::create('site_minute_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->dateTime('stat_minute');
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('entries')->default(0);

            $table->unique(['site_id', 'stat_minute']);
            $table->index('stat_minute');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_minute_stats');
    }
};
