<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy ooinuza status lookup (0=OFFLINE, 1=ONLINE). Owned by niblirc;
     * exists here so the schema (and the FK from updatepacklist_bots) is
     * reproducible, and a no-op against a database that already has it.
     */
    public function up(): void
    {
        if (Schema::hasTable('updatepacklist_status')) {
            return;
        }

        Schema::create('updatepacklist_status', function (Blueprint $table) {
            $table->integer('id');
            $table->string('description', 20);
            $table->primary('id');
        });

        DB::table('updatepacklist_status')->insert([
            ['id' => 0, 'description' => 'OFFLINE'],
            ['id' => 1, 'description' => 'ONLINE'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('updatepacklist_status');
    }
};
