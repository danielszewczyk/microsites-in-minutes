<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A published one-page microsite. The owner's raw builder inputs and the
     * generated, render-ready content both live as JSON documents: they are
     * only ever read whole, by one template, and the database is the one place
     * a page survives a deploy (Cloud's disk is ephemeral).
     *
     * view_count / entry_count are denormalised onto the row so the public page
     * reads them without a join; the per-day breakdown lives in site_stats.
     */
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 72)->unique();
            $table->string('manage_token_hash', 64)->unique();
            $table->string('type')->default('standard');
            $table->string('status')->default('published');
            $table->json('inputs');
            $table->json('content');
            $table->json('settings');
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('entry_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
