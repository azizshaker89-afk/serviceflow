<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::set('company_name', 'ServiceFlow للصيانة');
        Setting::set('tax_rate', '14');
        Setting::set('currency', 'EGP');
    }
}
