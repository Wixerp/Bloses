<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Controller;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->notificationService->index(),
        ]);
    }

    public function markAsRead(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => $this->notificationService->markAsRead($id),
        ]);
    }

    public function markAllAsRead(): JsonResponse
    {
        $this->notificationService->markAllAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }
}
