<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A lead or sweepstakes entry captured by a microsite's form. is_amoe marks
     * a free "no purchase necessary" alternate method of entry. The IP is kept
     * only as a hash, for spam/rate-limit forensics, never the raw address.
     */
    public function up(): void
    {
        Schema::create('site_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('message')->nullable();
            $table->json('extra')->nullable();
            $table->boolean('is_amoe')->default(false);
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['site_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_entries');
    }
};
