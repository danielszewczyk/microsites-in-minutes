<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "require confirmation" add-on (double opt-in): an entry is created
     * unconfirmed with a token, the entrant clicks a link to confirm, and only
     * confirmed entries count as authenticated leads.
     */
    public function up(): void
    {
        Schema::table('site_entries', function (Blueprint $table) {
            $table->string('confirm_token', 64)->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_entries', function (Blueprint $table) {
            $table->dropColumn(['confirm_token', 'confirmed_at']);
        });
    }
};
