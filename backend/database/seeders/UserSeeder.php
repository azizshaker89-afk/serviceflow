<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Demo accounts (password: "password") — one per role, listed in the README.
     */
    public function run(): void
    {
        $demoAccounts = [
            ['name' => 'أحمد عبد الرحمن', 'email' => 'admin@serviceflow.test', 'role' => RoleName::Admin],
            ['name' => 'منى السيد', 'email' => 'reception@serviceflow.test', 'role' => RoleName::Receptionist],
            ['name' => 'محمود حسن', 'email' => 'technician@serviceflow.test', 'role' => RoleName::Technician],
            ['name' => 'سارة إبراهيم', 'email' => 'accountant@serviceflow.test', 'role' => RoleName::Accountant],
        ];

        foreach ($demoAccounts as $account) {
            $user = User::factory()->create(['name' => $account['name'], 'email' => $account['email']]);
            $user->assignRole($account['role']->value);
        }

        User::factory()->create(['name' => 'ياسمين فؤاد', 'email' => 'reception2@serviceflow.test'])
            ->assignRole(RoleName::Receptionist->value);

        TechnicianProfile::factory()->create([
            'user_id' => User::query()->where('email', 'technician@serviceflow.test')->value('id'),
            'specialization' => 'تكييف وتبريد',
            'hourly_rate' => '120.00',
        ]);

        $technicians = [
            ['كريم مصطفى', 'كهرباء', '100.00'],
            ['عمرو سعيد', 'سباكة', '90.00'],
            ['مصطفى علي', 'أجهزة منزلية', '110.00'],
            ['حسام فتحي', 'تكييف وتبريد', '130.00'],
            ['إسلام جمال', 'كهرباء', '100.00'],
            ['طارق نبيل', 'سباكة', '85.00'],
            ['وليد عادل', 'أجهزة منزلية', '115.00'],
            ['شريف أشرف', 'نجارة', '95.00'],
            ['هاني رضا', 'تكييف وتبريد', '125.00'],
        ];

        foreach ($technicians as $index => [$name, $specialization, $rate]) {
            $user = User::factory()->create(['name' => $name, 'email' => 'tech'.($index + 2).'@serviceflow.test']);
            $user->assignRole(RoleName::Technician->value);
            TechnicianProfile::factory()->create([
                'user_id' => $user->id,
                'specialization' => $specialization,
                'hourly_rate' => $rate,
            ]);
        }
    }
}
