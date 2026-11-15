<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;

class PublicServiceCategoryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(
            ServiceCategoryResource::collection(ServiceCategory::query()->active()->orderBy('id')->get()),
        );
    }
}
