<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Owner becomes the authenticated account: a password, email verification
     * and a remember token. This is what lets publishing require a verified
     * account (so a site's owner is proven, not just an email someone typed) and
     * what the MCP OAuth consent flow logs in as.
     */
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->string('password')->nullable()->after('name');
            $table->timestamp('email_verified_at')->nullable()->after('password');
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropColumn(['password', 'email_verified_at', 'remember_token']);
        });
    }
};
