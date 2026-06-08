<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            VendorSeeder::class,
            AdminUserSeeder::class,
            LicensePlanSeeder::class,
            OnlineStoreProductSeeder::class,
        ]);
    }
}
