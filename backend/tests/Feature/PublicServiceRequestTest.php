<?php

namespace Tests\Feature;

use App\Enums\RequestSource;
use App\Enums\ServiceRequestStatus;
use App\Models\Customer;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    private ServiceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ServiceCategory::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'لين عبد الناصر',
            'phone' => '01012345678',
            'address' => 'مدينة نصر، شارع عباس العقاد',
            'service_category_id' => $this->category->id,
            'description' => 'التكييف لا يبرد ويصدر صوتًا عاليًا',
            ...$overrides,
        ];
    }

    public function test_a_customer_can_submit_a_request_without_an_account(): void
    {
        $response = $this->postJson('/api/public/service-requests', $this->payload())
            ->assertCreated()
            ->assertJsonPath('success', true);

        $request = ServiceRequest::query()->sole();
        $response->assertJsonPath('data.code', $request->code);

        $this->assertSame(RequestSource::Online, $request->source);
        $this->assertNull($request->created_by);
        $this->assertSame(ServiceRequestStatus::New, $request->status);
        $this->assertSame('لين عبد الناصر', $request->customer->name);
        $this->assertSame(1, $request->statusHistories()->count());
    }

    public function test_phone_is_normalized_from_arabic_digits_and_country_code(): void
    {
        $this->postJson('/api/public/service-requests', $this->payload(['phone' => '+20 ١٠١ ٢٣٤ ٥٦٧٨']))->assertCreated();

        $this->assertSame('01012345678', Customer::query()->sole()->phone);
    }

    public function test_an_existing_customer_is_reused_but_never_overwritten_by_public_input(): void
    {
        $customer = Customer::factory()->create(['name' => 'الاسم الأصلي', 'phone' => '01012345678', 'address' => 'العنوان الأصلي']);

        $this->postJson('/api/public/service-requests', $this->payload(['name' => 'شخص آخر', 'address' => 'عنوان آخر']))
            ->assertCreated();

        $customer->refresh();
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame('الاسم الأصلي', $customer->name);
        $this->assertSame('العنوان الأصلي', $customer->address);
        $this->assertSame('عنوان آخر', $customer->serviceRequests()->sole()->address);
    }

    public function test_validation_errors_are_in_arabic(): void
    {
        $this->postJson('/api/public/service-requests', $this->payload(['phone' => '12345', 'description' => '']))
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.description.0', 'حقل وصف المشكلة مطلوب.')
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_the_honeypot_blocks_bots(): void
    {
        $this->postJson('/api/public/service-requests', $this->payload(['website' => 'http://spam.example']))
            ->assertUnprocessable();

        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_submissions_are_rate_limited_per_ip(): void
    {
        foreach (range(1, 3) as $i) {
            $this->postJson('/api/public/service-requests', $this->payload(['phone' => "0101234567{$i}"]))->assertCreated();
        }

        $this->postJson('/api/public/service-requests', $this->payload(['phone' => '01012345679']))->assertTooManyRequests();
    }

    public function test_tracking_requires_the_matching_phone(): void
    {
        $this->postJson('/api/public/service-requests', $this->payload())->assertCreated();
        $code = ServiceRequest::query()->sole()->code;

        $this->postJson('/api/public/service-requests/track', ['code' => $code, 'phone' => '01099999999'])
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->postJson('/api/public/service-requests/track', ['code' => strtolower($code), 'phone' => '01012345678'])
            ->assertOk()
            ->assertJsonPath('data.code', $code)
            ->assertJsonPath('data.status_label', 'جديد')
            ->assertJsonCount(1, 'data.timeline');
    }

    public function test_tracking_does_not_leak_internal_data(): void
    {
        $this->postJson('/api/public/service-requests', $this->payload())->assertCreated();
        $code = ServiceRequest::query()->sole()->code;

        $data = $this->postJson('/api/public/service-requests/track', ['code' => $code, 'phone' => '01012345678'])->json('data');

        $this->assertArrayNotHasKey('customer', $data);
        $this->assertArrayNotHasKey('note', $data['timeline'][0]);
        $this->assertArrayNotHasKey('changed_by', $data['timeline'][0]);
    }

    public function test_only_active_categories_are_listed_and_accepted(): void
    {
        $inactive = ServiceCategory::factory()->create(['is_active' => false]);

        $this->getJson('/api/public/service-categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->category->id);

        $this->postJson('/api/public/service-requests', $this->payload(['service_category_id' => $inactive->id]))
            ->assertJsonValidationErrors(['service_category_id']);
    }
}
