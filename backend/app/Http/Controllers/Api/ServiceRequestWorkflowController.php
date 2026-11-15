<?php

namespace App\Http\Controllers\Api;

use App\Actions\ServiceRequests\AssignTechnician;
use App\Actions\ServiceRequests\CancelServiceRequest;
use App\Actions\ServiceRequests\CompleteServiceRequest;
use App\Actions\ServiceRequests\LogWork;
use App\Actions\ServiceRequests\PauseForParts;
use App\Actions\ServiceRequests\ResumeWork;
use App\Actions\ServiceRequests\StartWork;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequests\AssignTechnicianRequest;
use App\Http\Requests\ServiceRequests\CancelServiceRequestRequest;
use App\Http\Requests\ServiceRequests\CompleteServiceRequestRequest;
use App\Http\Requests\ServiceRequests\LogWorkRequest;
use App\Http\Requests\ServiceRequests\PauseServiceRequestRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * One endpoint per workflow step. Each method is three lines: authorize with the policy,
 * hand the work to an Action, return the fresh request (with new allowed_actions).
 */
class ServiceRequestWorkflowController extends Controller
{
    public function assign(AssignTechnicianRequest $request, ServiceRequest $serviceRequest, AssignTechnician $action): JsonResponse
    {
        Gate::authorize('assign', $serviceRequest);
        $technician = User::query()->findOrFail($request->validated('technician_id'));
        $action->handle($serviceRequest, $technician, $request->user(), $request->validated('note'));

        return $this->respond($serviceRequest, "تم تعيين {$technician->name}");
    }

    public function start(Request $request, ServiceRequest $serviceRequest, StartWork $action): JsonResponse
    {
        Gate::authorize('start', $serviceRequest);
        $action->handle($serviceRequest, $request->user());

        return $this->respond($serviceRequest, 'بدأ العمل على الطلب');
    }

    public function pause(PauseServiceRequestRequest $request, ServiceRequest $serviceRequest, PauseForParts $action): JsonResponse
    {
        Gate::authorize('pause', $serviceRequest);
        $action->handle($serviceRequest, $request->user(), $request->validated('waiting_for'));

        return $this->respond($serviceRequest, 'تم إيقاف العمل لحين وصول القطع');
    }

    public function resume(Request $request, ServiceRequest $serviceRequest, ResumeWork $action): JsonResponse
    {
        Gate::authorize('resume', $serviceRequest);
        $action->handle($serviceRequest, $request->user());

        return $this->respond($serviceRequest, 'تم استكمال العمل');
    }

    public function logWork(LogWorkRequest $request, ServiceRequest $serviceRequest, LogWork $action): JsonResponse
    {
        Gate::authorize('logWork', $serviceRequest);
        $action->handle($serviceRequest, $request->user()->load('technicianProfile'), (string) $request->validated('hours'), $request->validated('note'));

        return $this->respond($serviceRequest, 'تم تسجيل ساعات العمل', 201);
    }

    public function complete(CompleteServiceRequestRequest $request, ServiceRequest $serviceRequest, CompleteServiceRequest $action): JsonResponse
    {
        Gate::authorize('complete', $serviceRequest);
        $action->handle($serviceRequest, $request->user(), $request->boolean('no_parts_confirmed'), $request->validated('note'));

        return $this->respond($serviceRequest, 'تم إنهاء الصيانة. الطلب الآن عند المحاسب.');
    }

    public function cancel(CancelServiceRequestRequest $request, ServiceRequest $serviceRequest, CancelServiceRequest $action): JsonResponse
    {
        Gate::authorize('cancel', $serviceRequest);
        $action->handle($serviceRequest, $request->user(), $request->validated('reason'));

        return $this->respond($serviceRequest, 'تم إلغاء الطلب');
    }

    private function respond(ServiceRequest $serviceRequest, string $message, int $status = 200): JsonResponse
    {
        return ApiResponse::success(
            new ServiceRequestResource($serviceRequest->fresh()->load(ServiceRequestResource::RELATIONS)),
            $message,
            $status,
        );
    }
}
