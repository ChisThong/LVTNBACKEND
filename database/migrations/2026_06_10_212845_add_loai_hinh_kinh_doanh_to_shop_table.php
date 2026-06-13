<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shop', function (Blueprint $table) {
            $table->string('LoaiHinhKinhDoanh', 30)->default('ho_kinh_doanh')->after('ID_User')->comment('Loại hình kinh doanh: ho_kinh_doanh, doanh_nghiep');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop', function (Blueprint $table) {
            $table->dropColumn('LoaiHinhKinhDoanh');
        });
    }
};
