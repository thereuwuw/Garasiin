<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => 'password123',
            'role' => UserRole::Admin,
            'status' => UserStatus::Accepted,
        ]);

        User::forceCreate([
            'nik' => '3578010101900001',
            'name' => 'Bambang Santoso',
            'phone' => '081234567890',
            'email' => 'bambang@gmail.com',
            'password' => 'password',
            'role' => UserRole::Penyewa,
            'status' => UserStatus::Accepted,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        User::forceCreate([
            'nik' => '3578010202950002',
            'name' => 'Citra Lestari',
            'phone' => '081298765432',
            'email' => 'citra@gmail.com',
            'password' => 'password',
            'role' => UserRole::Penyewa,
            'status' => UserStatus::Pending,
        ]);

        User::forceCreate([
            'nik' => '3578010303970003',
            'name' => 'Dewi Anggraini',
            'phone' => '081311112222',
            'email' => 'dewi@gmail.com',
            'password' => 'password',
            'role' => UserRole::Penyewa,
            'status' => UserStatus::Rejected,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);
    }
}
