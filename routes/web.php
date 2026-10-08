<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Events\NotificationCreated;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/test-order-notification', function () {
    $notificationId = DB::table('notifications')->insertGetId([
        'user_id' => 36,
        'order_id' => 23,
        'payment_id' => null,
        'shipment_id' => null,
        'return_id' => null,
        'refund_id' => null,
        'title' => 'Order Placed',
        'message' => 'Your order ORD-123 has been placed successfully.',
        'action_url' => '/seller/orders/123',
        'is_read' => false,
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Test notification saved successfully.',
        'notification_id' => $notificationId,
    ]);
});


Route::get('/test-reverb', function () {
    NotificationCreated::dispatch(
        9999,
        36,
        3,
        123,
        null,
        null,
        null,
        'Test Realtime',
        'This is a realtime test notification.',
        null
    );

    return response()->json([
        'success' => true,
        'message' => 'NotificationCreated dispatched',
    ]);
});
