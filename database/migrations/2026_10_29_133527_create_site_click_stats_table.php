<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The durable click roll-up: one row per site per tracked element per day,
     * incremented forward-only as clicks happen (mirroring site_stats). Keyed by
     * the element's stable publish-time id, so a click maps to a known element
     * even if its label changes. Long-term and never pruned — it is bounded
     * because the element set per site is small and fixed.
     */
    public function up(): void
    {
        Schema::create('site_click_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('element_id', 32);
            $table->date('stat_date');
            $table->unsignedBigInteger('clicks')->default(0);

            $table->unique(['site_id', 'element_id', 'stat_date']);
            $table->index('stat_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_click_stats');
    }
};
