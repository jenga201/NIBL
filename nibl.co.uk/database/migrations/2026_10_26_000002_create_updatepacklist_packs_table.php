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
        if (Schema::hasTable('updatepacklist_packs')) {
            return;
        }

        Schema::create('updatepacklist_packs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('bot_id');
            $table->integer('number');
            $table->string('name', 300);
            $table->string('size', 8);
            $table->bigInteger('sizekbits')->default(0);
            $table->integer('episode_number')->nullable();
            $table->dateTime('last_modified');

            $table->unique(['bot_id', 'number'], 'updatepacklist_packs_bot_number');
            $table->index('number', 'packid');
            $table->index('last_modified', 'last_modified');
            $table->index(['bot_id', 'sizekbits'], 'updatepacklist_packs_bot_sizekbits');
            $table->index(['bot_id', 'name'], 'bot_name');
            $table->foreign('bot_id', 'updatepacklist_packs_bot_fk')
                ->references('id')->on('updatepacklist_bots')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('updatepacklist_packs');
    }
};
