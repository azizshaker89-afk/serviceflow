<?php

namespace App\Http\Controllers\Api;

use App\Actions\Inventory\AddPartToRequest;
use App\Actions\Inventory\RemovePartFromRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AddPartRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestPart;
use App\Models\SparePart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ServiceRequestPartController extends Controller
{
    public function store(AddPartRequest $request, ServiceRequest $serviceRequest, AddPartToRequest $action): JsonResponse
    {
        Gate::authorize('manageParts', $serviceRequest);

        $part = SparePart::query()->findOrFail($request->validated('spare_part_id'));
        $action->handle($serviceRequest, $part, $request->integer('quantity'), $request->user());

        return $this->respond($serviceRequest, "تم صرف {$request->integer('quantity')} × {$part->name}", 201);
    }

    public function destroy(Request $request, ServiceRequest $serviceRequest, ServiceRequestPart $part, RemovePartFromRequest $action): JsonResponse
    {
        Gate::authorize('manageParts', $serviceRequest);

        $action->handle($serviceRequest, $part, $request->user());

        return $this->respond($serviceRequest, 'تم إرجاع القطعة للمخزن');
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
