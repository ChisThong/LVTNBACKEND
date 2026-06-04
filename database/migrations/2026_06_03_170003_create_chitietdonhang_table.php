<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chitietdonhang')) {
            return;
        }

        Schema::create('chitietdonhang', function (Blueprint $table) {
            $table->increments('ID_ChiTiet');
            $table->unsignedInteger('ID_DonHang');
            $table->unsignedInteger('ID_SanPham');
            $table->unsignedInteger('SoLuong');          // >= 1
            $table->decimal('DonGia', 15, 2);            // Giá tại thời điểm đặt hàng

            $table->foreign('ID_DonHang')->references('ID_DonHang')->on('donhang')
                  ->onDelete('cascade');
            $table->foreign('ID_SanPham')->references('ID_SanPham')->on('sanpham')
                  ->onDelete('restrict');                // Không xoá sản phẩm khi đã có đơn hàng
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chitietdonhang');
    }
};
