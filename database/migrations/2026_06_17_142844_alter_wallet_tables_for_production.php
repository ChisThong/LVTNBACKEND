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
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->enum('status', ['pending', 'success', 'failed'])->default('success')->after('type');
            $table->decimal('balance_before', 15, 2)->nullable()->change();
            $table->decimal('balance_after', 15, 2)->nullable()->change();
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->integer('admin_id')->nullable()->after('status')->comment('ID of admin who processed this');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->decimal('balance_before', 15, 2)->nullable(false)->change();
            $table->decimal('balance_after', 15, 2)->nullable(false)->change();
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn('admin_id');
        });
    }
};
