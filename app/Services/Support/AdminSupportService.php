<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\DB;
use App\Events\ChatMessageSent;
use Illuminate\Support\Facades\Storage;

class AdminSupportService
{
    public function getTickets(array $filters = [])
    {
        $latestTicketIds = DB::table('support_tickets as st')
            ->selectRaw('DISTINCT ON (st.user_id) st.id')
            ->leftJoin('support_ticket_replies as lr', function ($join) {
                $join->on('lr.id', '=', DB::raw('(SELECT r.id FROM support_ticket_replies r WHERE r.support_ticket_id = st.id
                    ORDER BY r.created_at DESC, r.id DESC LIMIT 1)'));
            })->orderBy('st.user_id')
            ->orderByRaw('COALESCE(lr.created_at, st.last_reply_at, st.created_at) DESC')->orderByDesc('st.id');

        $query = DB::table('support_tickets')
            ->joinSub($latestTicketIds, 'latest_ticket', function ($join) {
                $join->on('latest_ticket.id', '=', 'support_tickets.id');
            })
            ->leftJoin('users as seller', 'seller.id', '=', 'support_tickets.user_id')
            ->leftJoin('support_ticket_replies as latest_reply', function ($join) {
                $join->on('latest_reply.id', '=',
                    DB::raw('(
                        SELECT r.id
                        FROM support_ticket_replies r
                        WHERE r.support_ticket_id = support_tickets.id
                        ORDER BY r.created_at DESC, r.id DESC
                        LIMIT 1
                    )')
                );
            })
            ->select(
                'support_tickets.id','support_tickets.user_id','support_tickets.subject','support_tickets.message',
                'support_tickets.image','support_tickets.status_id','support_tickets.created_at','support_tickets.last_reply_at',
                'support_tickets.resolved_at','seller.name as seller_name','seller.email as seller_email',
                'latest_reply.message as latest_message','latest_reply.sender_type as latest_sender_type',
                'latest_reply.user_id as latest_sender_id',
                DB::raw('COALESCE(latest_reply.created_at, support_tickets.last_reply_at, support_tickets.created_at) as last_message_at')
            );

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('support_tickets.subject', 'ILIKE', "%{$search}%")
                    ->orWhere('support_tickets.message', 'ILIKE', "%{$search}%")
                    ->orWhere('seller.name', 'ILIKE', "%{$search}%")
                    ->orWhere('seller.email', 'ILIKE', "%{$search}%");
            });
        }

        if (isset($filters['status_id']) && $filters['status_id'] !== '') {
            $query->where(
                'support_tickets.status_id',
                (int) $filters['status_id']
            );
        }

        $query->orderByRaw('COALESCE(latest_reply.created_at, support_tickets.last_reply_at, support_tickets.created_at) DESC')
        ->orderByDesc('support_tickets.id');
        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->paginate($perPage);
    }

    public function getTicketById(int $ticketId): array
    {
        // Selected ticket se seller ki user_id nikalo
        $selectedTicket = DB::table('support_tickets')
            ->where('id', $ticketId)
            ->first();

        abort_if(!$selectedTicket, 404, 'Support ticket not found.');

        $sellerId = $selectedTicket->user_id;

        // Seller ke tamam tickets
        $tickets = DB::table('support_tickets')
            ->leftJoin('users as seller', 'seller.id', '=', 'support_tickets.user_id')
            ->where('support_tickets.user_id', $sellerId)
            ->select(
                'support_tickets.id',
                'support_tickets.user_id',
                'support_tickets.subject',
                'support_tickets.message',
                'support_tickets.last_reply_at',
                'support_tickets.image',
                'support_tickets.created_at',
                'support_tickets.updated_at',
                'seller.name as seller_name',
                'seller.email as seller_email'
            )
            ->orderBy('support_tickets.created_at')
            ->orderBy('support_tickets.id')
            ->get();

        // Har ticket ki image URL
        foreach ($tickets as $ticket) {
            $ticket->image_url = $ticket->image
                ? Storage::disk('public')->url($ticket->image)
                : null;
        }

        // Seller ke tamam tickets ki seller/admin chat history
        $replies = DB::table('support_ticket_replies as r')
            ->join('support_tickets as t', 't.id', '=', 'r.support_ticket_id')
            ->leftJoin('users as sender', 'sender.id', '=', 'r.user_id')
            ->where('t.user_id', $sellerId)
            ->select(
                'r.id',
                'r.support_ticket_id',
                'r.user_id',
                'r.sender_type',
                'r.message',
                'r.image',
                'r.created_at',
                'r.updated_at',
                'sender.name as sender_name',
                'sender.email as sender_email'
            )
            ->orderBy('r.created_at')
            ->orderBy('r.id')
            ->get()
            ->map(function ($reply) {
                $reply->image_url = $reply->image
                    ? Storage::disk('public')->url($reply->image)
                    : null;

                return $reply;
            });

        return [
            'seller_id' => $sellerId,
            'tickets' => $tickets,
            'replies' => $replies,
        ];
    }

    public function replyToTicket(
        int $ticketId,
        int $adminId,
        string $message
    ): object {
        [$ticket, $reply] = DB::transaction(function () use (
            $ticketId,
            $adminId,
            $message
        ) {
            $ticket = DB::table('support_tickets')
                ->where('id', $ticketId)
                ->lockForUpdate()
                ->first();

            abort_if(!$ticket, 404, 'Support ticket not found.');

            $now = now();

            $replyId = DB::table('support_ticket_replies')->insertGetId([
                'support_ticket_id' => $ticketId,
                'user_id' => $adminId,
                'sender_type' => 'admin',
                'message' => $message,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('support_tickets')
                ->where('id', $ticketId)
                ->update([
                    'last_reply_at' => $now,
                    'updated_at' => $now,
                ]);

            $reply = DB::table('support_ticket_replies')
                ->leftJoin(
                    'users as sender',
                    'sender.id',
                    '=',
                    'support_ticket_replies.user_id'
                )
                ->where('support_ticket_replies.id', $replyId)
                ->select(
                    'support_ticket_replies.*',
                    'sender.name as sender_name',
                    'sender.email as sender_email'
                )
                ->first();

            return [$ticket, $reply];
        });

        // Broadcast after transaction commits.
        // Buyer-Seller and Seller Support both use chat.{ticketId}.
        $chatMessage = [
            'id' => (int) $reply->id,
            'support_ticket_id' => (int) $reply->support_ticket_id,
            'user_id' => (int) $reply->user_id,
            'sender_type' => 'admin',
            'message' => $reply->message,
            'image' => $reply->image ?? null,
            'image_url' => !empty($reply->image)
                ? Storage::disk('public')->url($reply->image)
                : null,
            'sender_name' => $reply->sender_name,
            'created_at' => $reply->created_at,
        ];

        event(new \App\Events\ChatMessageSent(
            (int) $ticketId,
            $chatMessage
        ));

        return $reply;
    }

    public function updateTicketStatus(
        int $ticketId,
        int $statusId
    ): object {
        $ticket = DB::table('support_tickets')
            ->where('id', $ticketId)
            ->first();

        abort_if(!$ticket, 404, 'Support ticket not found.');

        $now = now();

        DB::table('support_tickets')
            ->where('id', $ticketId)
            ->update([
                'status_id' => $statusId,
                'resolved_at' => $this->isResolvedStatus($statusId)
                    ? $now
                    : null,
                'updated_at' => $now,
            ]);

        return DB::table('support_tickets')
            ->where('id', $ticketId)
            ->first();
    }

    private function isResolvedStatus(int $statusId): bool
    {
        $resolvedStatusIds = config(
            'support.resolved_status_ids',
            []
        );

        return in_array(
            $statusId,
            $resolvedStatusIds,
            true
        );
    }
}
