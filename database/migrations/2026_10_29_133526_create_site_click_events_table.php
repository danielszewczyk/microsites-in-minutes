<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The raw click firehose for the Pro click-tracking add-on: one short-lived
     * row per accepted click on a tracked link or button. Anonymous and
     * cookieless — only the stable element id (assigned at publish time) and the
     * link's href, never a visitor identity, IP or user agent. Kept for a short
     * retention window and pruned by stats:prune; the durable counts live in the
     * forward-only daily roll-up (site_click_stats), so this never becomes a
     * raw-clicks-forever table.
     */
    public function up(): void
    {
        Schema::create('site_click_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('element_id', 32);
            $table->string('href', 512)->nullable();
            $table->dateTime('occurred_at');

            $table->index('occurred_at');
            $table->index(['site_id', 'element_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_click_events');
    }
};
