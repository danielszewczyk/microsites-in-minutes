<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vanity domains bought for a site. One active domain serves one site; the
     * app routes by Host. status moves pending_dns → active once the domain's
     * DNS points at us and the certificate is issued (a stub step until the
     * registrar + Cloudflare for SaaS are wired at deploy).
     */
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('status')->default('pending_dns');
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('cost_cents')->default(0);
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
