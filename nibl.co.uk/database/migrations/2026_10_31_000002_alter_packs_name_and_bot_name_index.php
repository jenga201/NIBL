<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bring an already-adopted updatepacklist_packs up to date with the
     * canonical schema: narrow `name` from TEXT to VARCHAR(300) (indexable) and
     * add the (bot_id, name) covering key. Idempotent — a no-op on hosts that
     * already applied this and on SQLite (the fresh-create migration already
     * builds the final shape there).
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('updatepacklist_packs')) {
            return;
        }

        $col = DB::selectOne(
            "SELECT DATA_TYPE, IS_NULLABLE FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'updatepacklist_packs'
             AND column_name = 'name'"
        );

        if ($col !== null && strtolower((string) $col->DATA_TYPE) !== 'varchar') {
            $nullable = $col->IS_NULLABLE === 'YES' ? '' : ' NOT NULL';
            DB::statement('ALTER TABLE updatepacklist_packs MODIFY name VARCHAR(300)'.$nullable);
        }

        $idx = DB::selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = 'updatepacklist_packs'
             AND index_name = 'bot_name'"
        );

        if ($idx === null || (int) $idx->c === 0) {
            DB::statement('ALTER TABLE updatepacklist_packs ADD KEY bot_name (bot_id, name)');
        }
    }

    public function down(): void
    {
        // Forward-only normalization of an adopted table; nothing to undo.
    }
};
