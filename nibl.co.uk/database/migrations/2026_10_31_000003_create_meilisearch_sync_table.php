<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sync watermark for the Meilisearch read model: one row per bot recording
     * the attributes at the time it was last indexed, so the minute sync worker
     * can detect pack changes (last_processed), status flips and informative
     * flips cheaply.
     */
    public function up(): void
    {
        if (Schema::hasTable('meilisearch_sync')) {
            return;
        }

        Schema::create('meilisearch_sync', function (Blueprint $table) {
            $table->integer('bot_id')->primary();
            $table->dateTime('last_processed');
            $table->tinyInteger('status_id');
            $table->tinyInteger('informative');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meilisearch_sync');
    }
};
