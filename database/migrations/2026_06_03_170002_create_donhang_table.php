<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('donhang')) {
            return;
        }

        Schema::create('donhang', function (Blueprint $table) {
            $table->increments('ID_DonHang');
            $table->unsignedInteger('ID_User');                      // Người mua
            $table->string('DiaChiGiao', 255);                       // Địa chỉ giao hàng (bắt buộc)
            $table->string('SDTNhanHang', 15);                       // SĐT nhận hàng (bắt buộc, hợp lệ)
            $table->decimal('TongTien', 15, 2)->default(0);
            $table->tinyInteger('TrangThai')->default(0)
                  ->comment('0=chờ xác nhận,1=xác nhận,2=đang giao,3=đã giao,4=đã huỷ');
            $table->timestamp('NgayDat')->useCurrent();
            $table->foreign('ID_User')->references('ID_User')->on('user')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donhang');
    }
};
