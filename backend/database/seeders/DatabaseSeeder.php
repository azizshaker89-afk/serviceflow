<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Model events stay enabled on purpose: request and invoice codes are generated in model events.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingSeeder::class,
            UserSeeder::class,
            ServiceCategorySeeder::class,
            CustomerSeeder::class,
            SparePartSeeder::class,
            ServiceRequestSeeder::class,
        ]);
    }
}
