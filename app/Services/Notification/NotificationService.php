<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function create(array $data)
    {
        $typeId = DB::table('master_types')->where('key', 'notification_type')->where('value', $data['type'])->value('id');

        if (!$typeId) {
            throw new \Exception(
                "Notification type '{$data['type']}' not found."
            );
        }

        $notificationId = DB::table('notifications')->insertGetId([
            'user_id' => $data['user_id'],
            'type_id' => $typeId,
            'order_id' => $data['order_id'] ?? null,
            'payment_id' => $data['payment_id'] ?? null,
            'shipment_id' => $data['shipment_id'] ?? null,
            'return_id' => $data['return_id'] ?? null,
            'refund_id' => $data['refund_id'] ?? null,
            'title' => $data['title'],
            'message' => $data['message'],
            'action_url' => $data['action_url'] ?? null,
            'is_read' => false,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('notifications')
            ->leftJoin('master_types as type','type.id','=','notifications.type_id')
            ->where('notifications.id', $notificationId)
            ->select([
                'notifications.*','type.value as type','type.description as type_name',
            ])
            ->first();
    }

    public function index()
    {
        if (!auth()->check()) {
            abort(401, 'Please login.');
        }

        $userId = auth()->id();

        return DB::table('notifications')
            ->leftJoin('master_types as type','type.id','=','notifications.type_id')
            ->where('notifications.user_id', $userId)
            ->select([
                'notifications.id','notifications.user_id','notifications.type_id',
                'type.value as type','type.description as type_name',
                'notifications.order_id','notifications.payment_id','notifications.shipment_id','notifications.return_id',
                'notifications.refund_id','notifications.title','notifications.message','notifications.action_url',
                'notifications.is_read','notifications.read_at','notifications.created_at',
            ])->orderByDesc('notifications.id')->limit(50)->get();
    }

    public function markAsRead(int $id)
    {
        $notification = DB::table('notifications')->where('id', $id)->where('user_id', auth()->id())->first();

        if (!$notification) {
            abort(404, 'Notification not found.');
        }

        DB::table('notifications')->where('id', $id)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return DB::table('notifications')->where('id', $id)->first();
    }

    public function markAllAsRead()
    {
        DB::table('notifications')->where('user_id', auth()->id())->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return true;
    }
}
