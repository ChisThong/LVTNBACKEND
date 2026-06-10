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
        if (Schema::hasTable('user')) {
            return;
        }

        Schema::create('user', function (Blueprint $table) {
            $table->increments('ID_User');
            $table->string('HoTen', 100);
            $table->string('email', 100)->unique();
            $table->string('diachi', 255)->nullable();
            $table->string('sdt', 15)->nullable();
            $table->string('matkhau');
            $table->tinyInteger('TrangThai')->default(1)->comment('1=active, 0=inactive');
            $table->timestamp('ngaydangki')->useCurrent();
            $table->unsignedInteger('ID_role')->default(2)->comment('2=NguoiMua');
            $table->foreign('ID_role')->references('ID_role')->on('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user');
    }
};
