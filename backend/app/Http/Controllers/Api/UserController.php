<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\ManagedUserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Staff accounts. Accounts are deactivated, never deleted: their name stays on every
 * history row, work log, stock movement and payment they ever recorded.
 */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $validated = $request->validate([
            'role' => ['nullable', Rule::enum(RoleName::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $users = User::query()
            ->with(['roles', 'technicianProfile'])
            ->withCount(['assignedServiceRequests as open_requests_count' => fn (Builder $q) => $q->open()])
            ->when($validated['role'] ?? null, fn (Builder $q, string $role) => $q->role($role))
            ->when($validated['search'] ?? null, fn (Builder $q, string $term) => $q->where(
                fn (Builder $q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")
            ))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(ManagedUserResource::collection($users));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $user = DB::transaction(function () use ($request): User {
            $user = User::query()->create($request->safe()->only(['name', 'email', 'phone', 'password']));
            $user->assignRole($request->validated('role'));

            if ($request->validated('role') === RoleName::Technician->value) {
                $user->technicianProfile()->create($request->safe()->only(['specialization', 'hourly_rate']));
            }

            return $user;
        });

        $this->log($request, $user, 'created', "إضافة حساب {$user->name}");

        return ApiResponse::success($this->present($user), "تم إنشاء حساب {$user->name}", 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $data = $request->validated();
        $this->guardRules($request->user(), $user, $data);

        DB::transaction(function () use ($user, $data): void {
            $user->fill(collect($data)->only(['name', 'email', 'phone', 'is_active'])->all());

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            $user->save();

            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
            }

            $profile = collect($data)->only(['specialization', 'hourly_rate'])->filter(fn ($v) => $v !== null)->all();

            if ($profile !== [] && $user->isTechnician()) {
                $user->technicianProfile()->updateOrCreate(['user_id' => $user->id], $profile);
            }
        });

        $this->log($request, $user, 'updated', "تعديل حساب {$user->name}", array_keys($data));

        return ApiResponse::success($this->present($user), 'تم حفظ التعديلات');
    }

    /**
     * Business rules that keep the system usable after an admin's edit.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardRules(User $actor, User $user, array $data): void
    {
        $deactivating = array_key_exists('is_active', $data) && ! $data['is_active'] && $user->is_active;
        $changingRole = isset($data['role']) && ! $user->hasRole($data['role']);

        if ($actor->is($user) && ($deactivating || $changingRole)) {
            throw new BusinessRuleException('لا يمكنك إيقاف حسابك أو تغيير دورك بنفسك.');
        }

        $leavingAdmin = $user->hasRole(RoleName::Admin->value) && ($deactivating || $changingRole);

        if ($leavingAdmin && User::role(RoleName::Admin->value)->active()->count() <= 1) {
            throw new BusinessRuleException('لا بد أن يبقى مدير نظام نشط واحد على الأقل.');
        }

        $leavingTechnician = $user->isTechnician() && ($deactivating || $changingRole);
        $openJobs = $leavingTechnician ? $user->assignedServiceRequests()->open()->count() : 0;

        if ($openJobs > 0) {
            throw new BusinessRuleException("لدى الفني {$openJobs} طلبات مفتوحة. أعد تعيينها لفني آخر أولًا.");
        }
    }

    private function present(User $user): ManagedUserResource
    {
        return new ManagedUserResource(
            $user->fresh()->load(['roles', 'technicianProfile'])
                ->loadCount(['assignedServiceRequests as open_requests_count' => fn (Builder $q) => $q->open()])
        );
    }

    /**
     * @param  list<string>  $changes
     */
    private function log(Request $request, User $user, string $event, string $text, array $changes = []): void
    {
        activity('users')->performedOn($user)->causedBy($request->user())->event($event)
            ->withProperties(array_values(array_diff($changes, ['password'])) ? ['changes' => array_values(array_diff($changes, ['password']))] : [])
            ->log($text);
    }
}
