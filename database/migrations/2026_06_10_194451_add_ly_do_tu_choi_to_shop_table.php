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
            $table->text('LyDoTuChoi')->nullable()->after('TrangThaiDuyet')->comment('Lý do từ chối khi duyệt gian hàng');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop', function (Blueprint $table) {
            $table->dropColumn('LyDoTuChoi');
        });
    }
};
