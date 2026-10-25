<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The built-in analytics: one row per site per day. Views are rolled up by
     * a queued job so that serving a cached page never blocks on a write.
     */
    public function up(): void
    {
        Schema::create('site_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->date('stat_date');
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('entries')->default(0);

            $table->unique(['site_id', 'stat_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_stats');
    }
};
