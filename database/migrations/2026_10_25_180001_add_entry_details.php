<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Richer context on each entry for the owner to qualify leads: the raw IP,
     * user agent and country (from Cloudflare's CF-IPCountry header in prod),
     * plus an optional email-deliverability result when the owner turns on
     * verification. Note: the raw IP is personal data — kept because it is the
     * owner's own lead record; the hash column stays for rate-limit forensics.
     */
    public function up(): void
    {
        Schema::table('site_entries', function (Blueprint $table) {
            $table->string('ip')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('email_status')->nullable();
            $table->unsignedTinyInteger('email_score')->nullable();
            $table->string('phone_status')->nullable();
            $table->string('phone_line')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_entries', function (Blueprint $table) {
            $table->dropColumn(['ip', 'user_agent', 'country', 'email_status', 'email_score', 'phone_status', 'phone_line']);
        });
    }
};
