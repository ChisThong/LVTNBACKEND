<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm các cột hỗ trợ đăng nhập Google OAuth2 vào bảng user.
     *
     * - google_id : ID duy nhất từ Google (sub claim trong JWT)
     * - avatar    : URL ảnh đại diện lấy từ Google hoặc do user tự upload
     */
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table) {
            // Chỉ thêm nếu cột chưa tồn tại (an toàn khi chạy lại)
            if (! Schema::hasColumn('user', 'google_id')) {
                $table->string('google_id', 255)
                      ->nullable()
                      ->unique()
                      ->after('ID_role')
                      ->comment('Google OAuth2 sub – định danh duy nhất từ Google');
            }

            if (! Schema::hasColumn('user', 'avatar')) {
                $table->string('avatar', 512)
                      ->nullable()
                      ->after('google_id')
                      ->comment('URL ảnh đại diện (Google hoặc upload thủ công)');
            }
        });
    }

    /**
     * Xoá các cột đã thêm khi rollback.
     */
    public function down(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar']);
        });
    }
};
