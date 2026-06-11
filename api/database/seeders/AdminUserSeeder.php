<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
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
                    'password' => Hash::make($this->resolveSeedPassword()),
                    'role' => 'super_admin',
                    'vendor_id' => null,
                ]
            );
        }
    }

    private function resolveSeedPassword(): string
    {
        $configured = (string) env('ADMIN_SEED_PASSWORD', '');
        if ($configured !== '') {
            return $configured;
        }

        if (app()->environment('production')) {
            throw new \RuntimeException('ADMIN_SEED_PASSWORD must be configured in production.');
        }

        return Str::password(24);
    }
}
