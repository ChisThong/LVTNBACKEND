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
            // Drop old index first
            $table->dropIndex(['reference_type', 'reference_id']);
            // Add comprehensive unique constraint to prevent double insertion for the same action
            $table->unique(['wallet_id', 'reference_type', 'reference_id', 'type'], 'wallet_trans_unique_ref');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropUnique('wallet_trans_unique_ref');
            $table->index(['reference_type', 'reference_id']);
        });
    }
};
