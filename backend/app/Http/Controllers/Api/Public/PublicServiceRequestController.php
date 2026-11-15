<?php

namespace App\Http\Controllers\Api\Public;

use App\Actions\ServiceRequests\SubmitOnlineServiceRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StorePublicServiceRequestRequest;
use App\Http\Requests\Public\TrackServiceRequestRequest;
use App\Http\Resources\PublicServiceRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;

class PublicServiceRequestController extends Controller
{
    public function store(StorePublicServiceRequestRequest $request, SubmitOnlineServiceRequest $submit): JsonResponse
    {
        $serviceRequest = $submit->handle($request->validated());

        return ApiResponse::success(
            ['code' => $serviceRequest->code],
            'تم استلام طلبك. احتفظ برقم الطلب لمتابعته.',
            201,
        );
    }

    /**
     * Both the code and the phone must match, so codes cannot be enumerated.
     * A wrong phone and a wrong code return the same answer on purpose.
     */
    public function track(TrackServiceRequestRequest $request): JsonResponse
    {
        $serviceRequest = ServiceRequest::query()
            ->where('code', $request->validated('code'))
            ->whereHas('customer', fn ($query) => $query->where('phone', $request->validated('phone')))
            ->with(['category', 'statusHistories', 'invoice'])
            ->first();

        if ($serviceRequest === null) {
            return ApiResponse::error('لم نجد طلبًا بهذا الرقم ورقم الموبايل. تأكد من البيانات وحاول مرة أخرى.', 404);
        }

        return ApiResponse::success(new PublicServiceRequestResource($serviceRequest));
    }
}
