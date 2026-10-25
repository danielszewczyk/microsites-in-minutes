<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Time-on-page: the page measures how long it was actively visible and
     * sends it on exit. We keep a running total and a count per site so the
     * dashboard can show the average dwell time.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->unsignedBigInteger('dwell_ms_total')->default(0);
            $table->unsignedBigInteger('dwell_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['dwell_ms_total', 'dwell_count']);
        });
    }
};
