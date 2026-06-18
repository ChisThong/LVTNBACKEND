<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class WalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo một user demo nếu chưa có
        $user = User::firstOrCreate(
            ['email' => 'walletdemo@gmail.com'],
            [
                'HoTen' => 'Ví Demo User',
                'sdt' => '0999999999',
                'matkhau' => Hash::make('password123'),
                'ID_role' => 2, // NguoiMua
                'TrangThai' => 1
            ]
        );

        // Tạo ví
        Wallet::firstOrCreate(
            ['user_id' => $user->ID_User],
            ['balance' => 1000000, 'frozen_balance' => 0]
        );
    }
}
