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
        Schema::table('sanpham', function (Blueprint $table) {
            if (!Schema::hasColumn('sanpham', 'TrangThaiDuyet')) {
                $table->string('TrangThaiDuyet', 50)->default('cho_duyet')->after('TrangThai');
            }
            if (!Schema::hasColumn('sanpham', 'LyDoTuChoi')) {
                $table->text('LyDoTuChoi')->nullable()->after('TrangThaiDuyet');
            }
            if (!Schema::hasColumn('sanpham', 'NgayDuyet')) {
                $table->timestamp('NgayDuyet')->nullable()->after('LyDoTuChoi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sanpham', function (Blueprint $table) {
            if (Schema::hasColumn('sanpham', 'TrangThaiDuyet')) {
                $table->dropColumn('TrangThaiDuyet');
            }
            if (Schema::hasColumn('sanpham', 'LyDoTuChoi')) {
                $table->dropColumn('LyDoTuChoi');
            }
            if (Schema::hasColumn('sanpham', 'NgayDuyet')) {
                $table->dropColumn('NgayDuyet');
            }
        });
    }
};
