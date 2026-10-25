<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Files the owner uploads for a site — handed off from their phone via the
     * QR on the dashboard: a desktop banner, a mobile banner, a copy deck and a
     * rules document. One row per slot per site; replacing a slot overwrites it.
     * The file lives on the 'assets' disk (a local folder in dev, a bucket on
     * Cloud); only its path is stored here.
     */
    public function up(): void
    {
        Schema::create('site_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('slot');
            $table->string('disk_path');
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->unique(['site_id', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_assets');
    }
};
