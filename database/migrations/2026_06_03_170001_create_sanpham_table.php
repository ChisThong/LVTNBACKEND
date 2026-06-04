<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sanpham')) {
            return;
        }

        Schema::create('sanpham', function (Blueprint $table) {
            $table->increments('ID_SanPham');
            $table->string('TenSP', 200);
            $table->text('MoTa')->nullable();
            $table->decimal('Gia', 15, 2)->unsigned();            // Giá > 0, không âm
            $table->unsignedInteger('SoLuong')->default(0);       // Số lượng >= 0, không âm
            $table->string('HinhAnh', 255)->nullable();
            $table->tinyInteger('TrangThai')->default(1)          // 1=đang bán, 0=ẩn
                  ->comment('1=active, 0=inactive');
            $table->timestamp('NgayTao')->useCurrent();
            $table->unsignedInteger('ID_User');                   // Người bán (NguoiBan)
            $table->foreign('ID_User')->references('ID_User')->on('user')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanpham');
    }
};
