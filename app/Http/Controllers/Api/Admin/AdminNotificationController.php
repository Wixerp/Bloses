<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AdminNotificationController extends Controller
{
    public function __construct(
        private AdminNotificationService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:200',
            'user_id' => 'nullable|integer|min:1',
            'type_id' => [
                'nullable',
                'integer',
                Rule::exists('master_types', 'id'),
            ],
            'is_read' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $result = $this->service->index($filters);

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully',
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Notification details retrieved successfully',
            'data' => $this->service->show($id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'type_id' => [
                'nullable',
                'integer',
                Rule::exists('master_types', 'id'),
            ],
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'action_url' => 'nullable|string|max:255',
        ]);

        $notification = $this->service->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Notification created successfully',
            'data' => $notification,
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully',
        ]);
    }

    public function announcements(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:200',
            'user_id' => 'nullable|integer|min:1',
            'is_read' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $result = $this->service->announcements($filters);

        return response()->json([
            'success' => true,
            'message' => 'Announcements retrieved successfully',
            'data' => $result['data'],
            'pagination' => $result['pagination'],
        ]);
    }

    public function storeAnnouncement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'action_url' => 'nullable|string|max:255',
            'role_id' => [
                'nullable',
                'integer',
                Rule::in([2, 3]),
            ],
        ]);

        $result = $this->service->createAnnouncement($data);

        return response()->json([
            'success' => true,
            'message' => 'Announcement sent successfully',
            'data' => $result,
        ], 201);
    }
}
