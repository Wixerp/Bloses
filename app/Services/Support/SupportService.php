<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\DB;

class SupportService
{
    public function createTicket(array $data): array
    {
        $userId = auth()->id();

        if (!$userId) {
            abort(401, 'Please login first.');
        }

        $ticketId = DB::table('support_tickets')->insertGetId([
            'user_id' => $userId,
            'complaint_type_id' => $data['complaint_type_id'] ?? null,
            'priority_id' => $data['priority_id'] ?? null,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'id' => $ticketId,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'complaint_type_id' => $data['complaint_type_id'] ?? null,
            'priority_id' => $data['priority_id'] ?? null,
            'status_id' => null,
        ];
    }
}
