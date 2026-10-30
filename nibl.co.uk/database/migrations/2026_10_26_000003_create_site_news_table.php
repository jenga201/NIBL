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
        if (Schema::hasTable('site_news')) {
            return;
        }

        Schema::create('site_news', function (Blueprint $table) {
            $table->increments('sn_id');
            $table->string('sn_created_by', 100);
            $table->text('sn_context');
            $table->timestamp('sn_created_at')->useCurrent();
            $table->timestamp('sn_published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_news');
    }
};
