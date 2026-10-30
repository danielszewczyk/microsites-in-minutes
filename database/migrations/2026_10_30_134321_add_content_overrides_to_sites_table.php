<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The owner's inline copy edits: a map of content path (e.g. "hero.headline")
     * to the text they typed. Kept apart from the generated `content` document so
     * a regenerate replaces only the generated layer and the owner's words stay.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->json('content_overrides')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('content_overrides');
        });
    }
};
