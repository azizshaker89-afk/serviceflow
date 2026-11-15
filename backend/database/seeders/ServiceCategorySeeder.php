<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'صيانة تكييف', 'description' => 'شحن فريون، تنظيف، أعطال كمبروسر', 'default_fee' => null],
            ['name' => 'تركيب تكييف', 'description' => 'تركيب وحدة سبليت جديدة', 'default_fee' => '450.00'],
            ['name' => 'أعطال كهرباء', 'description' => 'قواطع، أسلاك، لوحات توزيع', 'default_fee' => null],
            ['name' => 'سباكة', 'description' => 'تسريبات، خلاطات، سخانات', 'default_fee' => null],
            ['name' => 'غسالات', 'description' => 'أعطال الغسالات الأوتوماتيك', 'default_fee' => null],
            ['name' => 'ثلاجات', 'description' => 'تبريد، ترموستات، تسريب غاز', 'default_fee' => null],
            ['name' => 'سخانات', 'description' => 'سخانات كهرباء وغاز', 'default_fee' => null],
            ['name' => 'معاينة', 'description' => 'زيارة معاينة وتشخيص فقط', 'default_fee' => '150.00'],
        ];

        foreach ($categories as $category) {
            ServiceCategory::query()->create($category);
        }
    }
}
