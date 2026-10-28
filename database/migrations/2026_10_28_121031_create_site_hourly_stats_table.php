<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-hour view/entry counts: the mid tier between minute and daily, kept
     * for ~90 days (pruned by stats:prune). Incremented forward-only as hits
     * happen, alongside the minute and daily tiers.
     */
    public function up(): void
    {
        Schema::create('site_hourly_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->dateTime('stat_hour');
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('entries')->default(0);

            $table->unique(['site_id', 'stat_hour']);
            $table->index('stat_hour');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_hourly_stats');
    }
};
