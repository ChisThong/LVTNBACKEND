<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Seed 3 roles mặc định cho Marketplace.
     */
    public function run(): void
    {
        DB::table('role')->insertOrIgnore([
            ['ID_role' => 1, 'Ten_role' => 'Admin'],
            ['ID_role' => 2, 'Ten_role' => 'NguoiBan'],
            ['ID_role' => 3, 'Ten_role' => 'NguoiMua'],
        ]);
    }
}
