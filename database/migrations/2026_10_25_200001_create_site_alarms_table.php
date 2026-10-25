<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Threshold alarms: email the owner when a site crosses a number of views,
     * entries or bytes of storage. Fires once (triggered_at), then stays quiet.
     * Every new site gets a default "100 views" alarm, enabled.
     */
    public function up(): void
    {
        Schema::create('site_alarms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('metric');
            $table->unsignedBigInteger('threshold');
            $table->string('email');
            $table->boolean('enabled')->default(true);
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_alarms');
    }
};
