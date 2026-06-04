<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles trước (vì user có foreign key tới role)
        $this->call(RoleSeeder::class);
    }
}
