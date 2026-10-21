<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuditLogController extends Controller
{
    public function __construct(
        private AuditLogService $service
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'user_id' => ['sometimes', 'integer', 'min:1'],
            'action' => ['sometimes', 'string', 'max:100'],
            'module' => ['sometimes', 'string', 'max:100'],
            'from_date' => ['sometimes', 'date_format:Y-m-d'],
            'to_date' => [
                'sometimes',
                'date_format:Y-m-d',
                'after_or_equal:from_date',
            ],
            'search' => ['sometimes', 'string', 'max:200'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $logs = $this->service->index($filters);

        return response()->json([
            'success' => true,
            'message' => 'Audit logs fetched successfully.',
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    public function show(int $id)
    {
        $log = $this->service->show($id);

        return response()->json([
            'success' => true,
            'message' => 'Audit log fetched successfully.',
            'data' => $log,
        ]);
    }
}
