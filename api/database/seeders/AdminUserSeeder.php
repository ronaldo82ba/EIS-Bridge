<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['email' => 'super_admin@eis-bridge.test', 'name' => 'Platform Admin'],
            ['email' => 'admin@eisbridge.ph', 'name' => 'Skeleton Admin'],
        ] as $admin) {
            User::firstOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'],
                    'password' => Hash::make('password'),
                    'role' => 'super_admin',
                    'vendor_id' => null,
                ]
            );
        }
    }
}
