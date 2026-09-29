<?php

namespace App\Http\Controllers\Api\MasterType;

use App\Http\Controllers\Controller;
use App\Http\Resources\MasterType\MasterTypeResource;
use App\Services\MasterType\MasterTypeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class MasterTypeController extends Controller
{
    public function __construct(
        protected MasterTypeService $masterTypeService
    ) {}
    
    public function getByKey(string $key): JsonResponse
    {
        $masterTypes = $this->masterTypeService->getByKey($key);
        return response()->json([
            'success' => true,
            'message' => 'Master type data retrieved successfully.',
            'data' => MasterTypeResource::collection($masterTypes),
        ]);
    }

}
