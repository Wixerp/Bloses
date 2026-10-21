<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class AdminNotificationService
{
    private function announcementTypeId(): int
    {
        $typeId = DB::table('master_types')
            ->where('key', 'notification_type')
            ->where('value', 'Announcement')
            ->where('is_active', true)
            ->value('id');

        if (!$typeId) {
            throw ValidationException::withMessages([
                'announcement' => [
                    'Announcement notification type is not configured.',
                ],
            ]);
        }

        return (int) $typeId;
    }

    public function index(array $filters = []): array
    {
        $query = DB::table('notifications as n')
            ->leftJoin('users as u', 'u.id', '=', 'n.user_id')
            ->leftJoin(
                'master_types as mt',
                'mt.id',
                '=',
                'n.type_id'
            )
            ->select(
                'n.id',
                'n.user_id',
                'u.name as user_name',
                'n.type_id',
                'mt.value as type',
                'n.title',
                'n.message',
                'n.action_url',
                'n.is_read',
                'n.read_at',
                'n.order_id',
                'n.payment_id',
                'n.shipment_id',
                'n.return_id',
                'n.refund_id',
                'n.created_at',
                'n.updated_at'
            );

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('n.title', 'ILIKE', "%{$search}%")
                    ->orWhere('n.message', 'ILIKE', "%{$search}%")
                    ->orWhere('u.name', 'ILIKE', "%{$search}%");
            });
        }

        if (isset($filters['user_id'])) {
            $query->where('n.user_id', $filters['user_id']);
        }

        if (isset($filters['type_id'])) {
            $query->where('n.type_id', $filters['type_id']);
        }

        if (isset($filters['is_read'])) {
            $query->where('n.is_read', $filters['is_read']);
        }

        $perPage = min(
            100,
            max(1, (int) ($filters['per_page'] ?? 15))
        );

        $paginator = $query
            ->orderByDesc('n.created_at')
            ->orderByDesc('n.id')
            ->paginate($perPage);

        return [
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public function show(int $id): object
    {
        $notification = DB::table('notifications as n')
            ->leftJoin('users as u', 'u.id', '=', 'n.user_id')
            ->leftJoin(
                'master_types as mt',
                'mt.id',
                '=',
                'n.type_id'
            )
            ->where('n.id', $id)
            ->select(
                'n.*',
                'u.name as user_name',
                'u.email as user_email',
                'mt.value as type'
            )
            ->first();

        if (!$notification) {
            throw (new ModelNotFoundException())
                ->setModel('notifications', [$id]);
        }

        return $notification;
    }

    public function create(array $data): object
    {
        $id = DB::table('notifications')->insertGetId([
            'user_id' => $data['user_id'],
            'type_id' => $data['type_id'] ?? null,
            'title' => $data['title'],
            'message' => $data['message'],
            'action_url' => $data['action_url'] ?? null,
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->show($id);
    }

    public function delete(int $id): void
    {
        if (!DB::table('notifications')->where('id', $id)->exists()) {
            throw (new ModelNotFoundException())
                ->setModel('notifications', [$id]);
        }

        DB::table('notifications')->where('id', $id)->delete();
    }

    public function announcements(array $filters = []): array
    {
        $filters['type_id'] = $this->announcementTypeId();

        return $this->index($filters);
    }

    public function createAnnouncement(array $data): array
    {
        $typeId = $this->announcementTypeId();

        // Assumes users.role_id identifies the user's role.
        // role_id 2 = seller, role_id 3 = buyer.
        $recipients = DB::table('users')
            ->whereIn('role_id', isset($data['role_id'])
                ? [(int) $data['role_id']]
                : [2, 3])
            ->select('id');

        $count = 0;

        DB::transaction(function () use (
            $recipients,
            $data,
            $typeId,
            &$count
        ) {
            $recipients->orderBy('id')->chunk(500, function ($users) use (
                $data,
                $typeId,
                &$count
            ) {
                $now = now();

                $rows = [];

                foreach ($users as $user) {
                    $rows[] = [
                        'user_id' => $user->id,
                        'type_id' => $typeId,
                        'title' => $data['title'],
                        'message' => $data['message'],
                        'action_url' => $data['action_url'] ?? null,
                        'is_read' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows) {
                    DB::table('notifications')->insert($rows);
                    $count += count($rows);
                }
            });
        });

        return [
            'recipients_count' => $count,
            'role_id' => $data['role_id'] ?? null,
            'title' => $data['title'],
            'message' => $data['message'],
        ];
    }
}
