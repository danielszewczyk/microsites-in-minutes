<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Built-in reporting breakdowns: cumulative views and entries per site,
     * split by a dimension (source, campaign, device). One flexible table keeps
     * the schema small while supporting "where it comes from and what converts".
     * Each entry also carries its own attribution for the export.
     */
    public function up(): void
    {
        Schema::create('site_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('dimension');
            $table->string('key');
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('entries')->default(0);

            $table->unique(['site_id', 'dimension', 'key']);
        });

        Schema::table('site_entries', function (Blueprint $table) {
            $table->string('source')->nullable();
            $table->string('campaign')->nullable();
            $table->string('device')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_breakdowns');

        Schema::table('site_entries', function (Blueprint $table) {
            $table->dropColumn(['source', 'campaign', 'device']);
        });
    }
};
