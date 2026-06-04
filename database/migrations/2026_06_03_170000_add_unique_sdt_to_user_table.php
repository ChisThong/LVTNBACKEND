<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm UNIQUE constraint cho cột sdt trong bảng user.
     * NULL được phép trùng (SQL standard).
     */
    public function up(): void
    {
        // Kiểm tra unique index đã tồn tại chưa bằng information_schema
        $exists = DB::select("
            SELECT COUNT(*) as cnt
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'user'
              AND index_name = 'user_sdt_unique'
        ");

        if ($exists[0]->cnt > 0) {
            return; // Index đã tồn tại, bỏ qua
        }

        Schema::table('user', function (Blueprint $table) {
            $table->unique('sdt', 'user_sdt_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropUnique('user_sdt_unique');
        });
    }
};
