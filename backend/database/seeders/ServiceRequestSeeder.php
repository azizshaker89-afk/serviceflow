<?php

namespace Database\Seeders;

use App\Actions\Billing\IssueInvoice;
use App\Actions\Billing\RecordPayment;
use App\Actions\Inventory\AddPartToRequest;
use App\Actions\Inventory\AdjustStock;
use App\Actions\ServiceRequests\AssignTechnician;
use App\Actions\ServiceRequests\CancelServiceRequest;
use App\Actions\ServiceRequests\CompleteServiceRequest;
use App\Actions\ServiceRequests\CreateServiceRequest;
use App\Actions\ServiceRequests\LogWork;
use App\Actions\ServiceRequests\PauseForParts;
use App\Actions\ServiceRequests\ResumeWork;
use App\Actions\ServiceRequests\StartWork;
use App\Enums\PaymentMethod;
use App\Enums\Priority;
use App\Enums\RequestSource;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\SparePart;
use App\Models\User;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Demo requests are produced by the same Actions the API uses, with the clock moved
 * to the moment each step "happened".
 *
 * Every request's story is broken into timed events; all events from all requests are
 * sorted and replayed in chronological order, so history, assignments, work logs and the
 * stock ledger read exactly as if the system had been in use for five months. Events
 * still in the future are dropped, which leaves some requests overdue or mid-work.
 */
class ServiceRequestSeeder extends Seeder
{
    private User $admin;

    /** @var Collection<int, User> */
    private Collection $receptionists;

    /** @var Collection<int, User> */
    private Collection $technicians;

    private User $demoTechnician;

    private User $accountant;

    /** @var list<array{0: Carbon, 1: Closure(): mixed}> */
    private array $events = [];

    /** @var array<string, string> category name => typical problem description */
    private const PROBLEMS = [
        'صيانة تكييف' => 'التكييف لا يبرد ويصدر صوتًا عاليًا من الوحدة الخارجية',
        'تركيب تكييف' => 'تركيب تكييف سبليت 1.5 حصان جديد في غرفة النوم',
        'أعطال كهرباء' => 'القاطع الرئيسي يفصل باستمرار عند تشغيل السخان',
        'سباكة' => 'تسريب مياه أسفل حوض المطبخ ورطوبة في الحائط',
        'غسالات' => 'الغسالة لا تصرف المياه في نهاية الدورة',
        'ثلاجات' => 'الثلاجة لا تبرد في الفريزر وصوت الموتور مرتفع',
        'سخانات' => 'السخان لا يسخن رغم وصول الكهرباء',
        'معاينة' => 'معاينة شقة قبل التشطيب لتحديد أعمال الكهرباء والسباكة',
    ];

    /** @var array<string, string> category name => SKU prefix of the parts it uses */
    private const PART_PREFIX = [
        'صيانة تكييف' => 'AC-',
        'تركيب تكييف' => 'AC-',
        'أعطال كهرباء' => 'EL-',
        'سباكة' => 'PL-',
        'غسالات' => 'WM-',
        'ثلاجات' => 'RF-',
        'سخانات' => 'HT-',
    ];

    public function run(): void
    {
        $this->admin = User::role(RoleName::Admin->value)->firstOrFail();
        $this->receptionists = User::role(RoleName::Receptionist->value)->get();
        $this->technicians = User::technicians()->with('technicianProfile')->get();
        $this->demoTechnician = $this->technicians->firstWhere('email', 'technician@serviceflow.test');
        $this->accountant = User::role(RoleName::Accountant->value)->firstOrFail();

        $categories = ServiceCategory::query()->get();
        $customers = Customer::query()->get();

        foreach (range(1, 180) as $i) {
            $receivedAt = fake()->boolean(12)
                ? now()->subMinutes(fake()->numberBetween(5, 8 * 60))
                : now()->subMinutes(fake()->numberBetween(8 * 60, 150 * 24 * 60));

            $this->story($receivedAt, $customers->random(), $categories->random(), $this->pickTechnician(), fake()->randomElement([
                'complete', 'complete', 'complete', 'complete', 'complete', 'complete',
                'waitThenComplete', 'waitThenComplete', 'cancelEarly', 'cancelLate',
            ]));
        }

        $this->guaranteeDemoTechnicianHasWork($customers, $categories);

        usort($this->events, fn (array $a, array $b) => $a[0]->getTimestamp() <=> $b[0]->getTimestamp());

        foreach ($this->events as [$moment, $event]) {
            $this->at($moment, $event);
        }
    }

    private function pickTechnician(): User
    {
        return fake()->boolean(22) ? $this->demoTechnician : $this->technicians->random();
    }

    /**
     * Queues one request's events. Events after "now" are not queued at all.
     *
     * @param  int|null  $stopAfter  Number of steps after creation to keep (used for the guaranteed demo queue).
     */
    private function story(Carbon $receivedAt, Customer $customer, ServiceCategory $category, User $technician, string $fate, ?Priority $priority = null, ?int $stopAfter = null): void
    {
        $story = (object) ['request' => null];
        $isOnline = $stopAfter === null && fake()->boolean(35);
        $priority ??= $isOnline ? Priority::Normal : fake()->randomElement(Priority::cases());

        $this->events[] = [$receivedAt->copy(), function () use ($story, $customer, $category, $priority, $isOnline): void {
            $story->request = app(CreateServiceRequest::class)->handle($customer, [
                'service_category_id' => $category->id,
                'description' => self::PROBLEMS[$category->name] ?? 'عطل يحتاج معاينة',
                'priority' => $priority,
                'address' => $customer->address,
            ], $isOnline ? null : $this->receptionists->random(), $isOnline ? RequestSource::Online : RequestSource::Staff);
        }];

        $request = fn (): ServiceRequest => $story->request;
        $usesParts = isset(self::PART_PREFIX[$category->name]) && fake()->boolean(75);

        $steps = [[fake()->numberBetween(15, 240), fn () => app(AssignTechnician::class)->handle($request(), $technician, $this->admin)]];

        if ($fate === 'cancelEarly') {
            $steps = [[fake()->numberBetween(20, 180), fn () => app(CancelServiceRequest::class)->handle($request(), $this->admin, 'العميل اعتذر عن الزيارة')]];
        } else {
            $steps[] = [fake()->numberBetween(40, 300), fn () => app(StartWork::class)->handle($request(), $technician)];
            $steps[] = [fake()->numberBetween(45, 150), fn () => app(LogWork::class)->handle($request(), $technician, fake()->randomElement(['1.00', '1.50', '2.00', '2.50']), 'فحص وتشخيص العطل')];

            if ($usesParts) {
                $steps[] = [fake()->numberBetween(5, 20), fn () => $this->issueParts($request(), $category, $technician)];
            }

            if ($fate === 'waitThenComplete') {
                $steps[] = [fake()->numberBetween(5, 30), fn () => app(PauseForParts::class)->handle($request(), $technician, 'قطعة غير متوفرة مع الفني')];
                $steps[] = [fake()->numberBetween(600, 2400), fn () => app(ResumeWork::class)->handle($request(), $technician)];
                $steps[] = [fake()->numberBetween(30, 120), fn () => app(LogWork::class)->handle($request(), $technician, '1.00', 'تركيب القطعة وتجربة الجهاز')];
            }

            $steps[] = $fate === 'cancelLate'
                ? [fake()->numberBetween(30, 240), fn () => app(CancelServiceRequest::class)->handle($request(), $this->admin, 'العميل رفض تكلفة الإصلاح بعد التشخيص')]
                : [fake()->numberBetween(10, 60), fn () => app(CompleteServiceRequest::class)->handle($request(), $technician, true, 'تمت الصيانة وتجربة الجهاز أمام العميل')];
        }

        if (in_array($fate, ['complete', 'waitThenComplete'], true)) {
            $billing = (object) ['invoice' => null];
            $steps[] = [fake()->numberBetween(30, 600), fn () => $billing->invoice = app(IssueInvoice::class)->handle($request(), $this->accountant, fake()->boolean(15) ? '50' : '0')];

            foreach ($this->paymentPlan() as [$minutes, $share, $method]) {
                $steps[] = [$minutes, fn () => $this->pay($billing->invoice, $share, $method)];
            }
        }

        if ($stopAfter !== null) {
            $steps = array_slice($steps, 0, $stopAfter);
        }

        $at = $receivedAt->copy();

        foreach ($steps as [$minutes, $step]) {
            $at->addMinutes($minutes);

            if ($at->isFuture()) {
                return;
            }

            $this->events[] = [$at->copy(), $step];
        }
    }

    /**
     * Most customers pay in full within a few days; some pay in two instalments; some have not paid yet.
     *
     * @return list<array{0: int, 1: string, 2: PaymentMethod}>
     */
    private function paymentPlan(): array
    {
        return match (fake()->randomElement(['full', 'full', 'full', 'full', 'instalments', 'deposit', 'none'])) {
            'full' => [[fake()->numberBetween(5, 3 * 24 * 60), 'all', fake()->randomElement(PaymentMethod::cases())]],
            'instalments' => [
                [fake()->numberBetween(5, 120), 'half', PaymentMethod::Cash],
                [fake()->numberBetween(2 * 24 * 60, 6 * 24 * 60), 'all', PaymentMethod::Wallet],
            ],
            'deposit' => [[fake()->numberBetween(5, 120), 'half', PaymentMethod::Cash]],
            default => [],
        };
    }

    private function pay(Invoice $invoice, string $share, PaymentMethod $method): void
    {
        $invoice->refresh();
        $balance = bcsub($invoice->total, $invoice->amount_paid, 2);
        $amount = $share === 'all' ? $balance : bcdiv($balance, '2', 2);

        if (bccomp($amount, '0', 2) > 0) {
            app(RecordPayment::class)->handle($invoice, $amount, $method, $this->accountant, $method === PaymentMethod::Cash ? null : 'REF-'.fake()->numerify('######'));
        }
    }

    /**
     * Issues one or two parts of the category's kind. When the shelf is empty the admin
     * buys more first, exactly as the store would, and that purchase lands in the ledger too.
     */
    private function issueParts(ServiceRequest $request, ServiceCategory $category, User $technician): void
    {
        $parts = SparePart::query()->where('sku', 'like', self::PART_PREFIX[$category->name].'%')->get();

        foreach ($parts->random(min($parts->count(), fake()->numberBetween(1, 2))) as $part) {
            $quantity = fake()->numberBetween(1, 2);

            try {
                app(AddPartToRequest::class)->handle($request, $part, $quantity, $technician);
            } catch (BusinessRuleException) {
                app(AdjustStock::class)->handle($part, StockMovementType::Purchase, max(10, $part->min_stock * 3), $this->admin, 'توريد عاجل بعد نفاد الرصيد');
                app(AddPartToRequest::class)->handle($request, $part, $quantity, $technician);
            }
        }
    }

    /**
     * Whatever the random timelines produced, the demo technician logs in to a realistic queue:
     * an urgent job already overdue, one in progress, and a fresh one just assigned.
     *
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, ServiceCategory>  $categories
     */
    private function guaranteeDemoTechnicianHasWork(Collection $customers, Collection $categories): void
    {
        $this->story(now()->subHours(6), $customers->random(), $categories->firstWhere('name', 'صيانة تكييف'), $this->demoTechnician, 'complete', Priority::Urgent, 1);
        $this->story(now()->subHours(4), $customers->random(), $categories->firstWhere('name', 'ثلاجات'), $this->demoTechnician, 'complete', Priority::High, 2);
        $this->story(now()->subMinutes(90), $customers->random(), $categories->firstWhere('name', 'غسالات'), $this->demoTechnician, 'complete', Priority::Normal, 1);
    }

    /**
     * @param  Closure(): mixed  $callback
     */
    private function at(Carbon $moment, Closure $callback): mixed
    {
        Carbon::setTestNow($moment);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
