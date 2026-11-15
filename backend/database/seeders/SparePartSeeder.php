<?php

namespace Database\Seeders;

use App\Actions\Inventory\AdjustStock;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Models\SparePart;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SparePartSeeder extends Seeder
{
    /**
     * Opening stock goes through the same AdjustStock action as a real purchase,
     * so the ledger explains the current balance from the very first row.
     */
    public function run(): void
    {
        $admin = User::role(RoleName::Admin->value)->firstOrFail();

        $parts = [
            ['AC-FRE-410', 'فريون R410 (كيلو)', '180.00', '260.00', 40, 10],
            ['AC-CAP-35', 'مكثف تكييف 35 ميكرو', '95.00', '150.00', 25, 5],
            ['AC-FAN-MTR', 'موتور مروحة وحدة خارجية', '650.00', '900.00', 8, 3],
            ['AC-REM-UNI', 'ريموت تكييف يونيفرسال', '60.00', '110.00', 30, 5],
            ['EL-BRK-32', 'قاطع كهرباء 32 أمبير', '85.00', '130.00', 50, 10],
            ['EL-WIR-6M', 'سلك نحاس 6 مم (متر)', '28.00', '40.00', 300, 50],
            ['EL-SOC-STD', 'بريزة حائط', '35.00', '60.00', 80, 15],
            ['PL-MIX-KIT', 'خلاط مطبخ', '420.00', '600.00', 12, 3],
            ['PL-VLV-HLF', 'محبس نصف بوصة', '55.00', '90.00', 40, 10],
            ['PL-HOS-50', 'خرطوم سخان 50 سم', '30.00', '55.00', 60, 10],
            ['WM-PMP-DRN', 'طلمبة صرف غسالة', '280.00', '400.00', 15, 4],
            ['WM-BLT-STD', 'سير غسالة', '90.00', '140.00', 20, 5],
            ['WM-DOOR-LK', 'كالون باب غسالة', '160.00', '240.00', 10, 3],
            ['RF-THR-STD', 'ترموستات ثلاجة', '210.00', '320.00', 14, 4],
            ['RF-GAS-134', 'غاز ثلاجة R134 (عبوة)', '150.00', '230.00', 20, 5],
            ['HT-ELM-2KW', 'شمعة سخان 2 كيلووات', '170.00', '260.00', 18, 5],
            ['HT-THR-STD', 'ترموستات سخان', '120.00', '190.00', 3, 5],
        ];

        /* Opening balances are dated before the five months of demo activity, so the ledger reads in order. */
        Carbon::setTestNow(now()->subDays(155)->setTime(9, 0));

        try {
            foreach ($parts as [$sku, $name, $cost, $price, $openingStock, $minStock]) {
                $part = SparePart::query()->create([
                    'sku' => $sku,
                    'name' => $name,
                    'unit_cost' => $cost,
                    'unit_price' => $price,
                    'min_stock' => $minStock,
                ]);

                app(AdjustStock::class)->handle($part, StockMovementType::Purchase, $openingStock, $admin, 'رصيد افتتاحي');
            }
        } finally {
            Carbon::setTestNow();
        }
    }
}
