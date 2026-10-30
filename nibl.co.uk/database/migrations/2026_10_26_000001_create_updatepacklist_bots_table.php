<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy ooinuza schema owned by the niblirc stack. This migration exists
     * so the test suite can build the table; it is a no-op against a database
     * that already has it.
     */
    public function up(): void
    {
        if (Schema::hasTable('updatepacklist_bots')) {
            return;
        }

        Schema::create('updatepacklist_bots', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('name', 40)->unique();
            $table->string('url', 200);
            $table->string('type', 10);
            $table->string('owner', 255)->nullable();
            $table->integer('status_id')->index();
            $table->dateTime('last_seen');
            $table->dateTime('last_processed');
            $table->tinyInteger('informative')->default(0);
            $table->boolean('batchenable')->default(true);
            $table->boolean('external')->default(false);
            $table->integer('parser_id')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('updatepacklist_bots');
    }
};
