<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm cột TrangThaiHienThi (hien/an) và LyDoAdminAn vào bảng sanpham.
     * Đây là trạng thái hiển thị do Admin kiểm soát — độc lập với TrangThai (Seller).
     */
    public function up(): void
    {
        Schema::table('sanpham', function (Blueprint $table) {
            if (!Schema::hasColumn('sanpham', 'TrangThaiHienThi')) {
                $table->enum('TrangThaiHienThi', ['hien', 'an'])
                      ->default('hien')
                      ->comment('Admin visibility: hien=công khai, an=admin ẩn')
                      ->after('TrangThaiDuyet');
            }
            if (!Schema::hasColumn('sanpham', 'LyDoAdminAn')) {
                $table->text('LyDoAdminAn')
                      ->nullable()
                      ->comment('Lý do Admin ẩn sản phẩm')
                      ->after('TrangThaiHienThi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sanpham', function (Blueprint $table) {
            if (Schema::hasColumn('sanpham', 'LyDoAdminAn')) {
                $table->dropColumn('LyDoAdminAn');
            }
            if (Schema::hasColumn('sanpham', 'TrangThaiHienThi')) {
                $table->dropColumn('TrangThaiHienThi');
            }
        });
    }
};
