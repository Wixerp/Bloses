<?php

namespace App\Services\Support;

use Illuminate\Http\UploadedFile;
use App\Events\ChatMessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SupportService
{
    public function createTicket(array $data): array
    {
        $userId = auth()->id();

        if (!$userId) {
            abort(401, 'Please login first.');
        }

        return DB::transaction(function () use ($data, $userId) {
            $now = now();
            $imagePath = null;

            if (isset($data['image']) && $data['image'] instanceof UploadedFile)
            {
                $imagePath = $data['image']->store('support-tickets/replies', 'public');
            }

            $ticket = null;

            if (!empty($data['ticket_id'])) {
                // Exact buyer-seller ticket.
                $ticket = DB::table('support_tickets')
                    ->where('id', (int) $data['ticket_id'])
                    ->where('conversation_type', 'buyer_seller')
                    ->where('seller_id', $userId)
                    ->first();

                // Buyer chat na mile to seller ka apna admin-support ticket check karo.
                if (!$ticket) {
                    $ticket = DB::table('support_tickets')
                        ->where('id', (int) $data['ticket_id'])
                        ->where('user_id', $userId)
                        ->where(function ($query) {
                            $query->where('conversation_type', 'support')
                                ->orWhereNull('conversation_type');
                        })
                        ->first();
                }

                abort_if(!$ticket, 404, 'Support ticket not found.');
            } else {
                // ID na ho to sirf admin-support ticket select karo.
                $ticket = DB::table('support_tickets')
                    ->where('user_id', $userId)
                    ->where(function ($query) {
                        $query->where('conversation_type', 'support')
                            ->orWhereNull('conversation_type');
                    })
                    ->orderByDesc('id')
                    ->first();
            }

            if ($ticket) {
                $replyId = DB::table('support_ticket_replies')
                    ->insertGetId([
                        'support_ticket_id' => $ticket->id,
                        'user_id' => $userId,
                        'sender_type' => 'seller',
                        'message' => $data['message'] ?? '',
                        'image' => $imagePath,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('support_tickets')
                    ->where('id', $ticket->id)
                    ->update([
                        'last_reply_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $chatMessage = [
                        'id' => (int) $replyId,
                        'support_ticket_id' => (int) $ticket->id,
                        'user_id' => (int) $userId,
                        'sender_type' => 'seller',
                        'message' => $data['message'] ?? '',
                        'image' => $imagePath,
                        'image_url' => $imagePath ? Storage::disk('public')->url($imagePath) : null,
                        'created_at' => $now->toISOString(),
                    ];


                    event(new ChatMessageSent((int) $ticket->id, $chatMessage));

                return [
                    'message' => 'Reply sent successfully.',
                    'ticket_id' => $ticket->id,
                    'reply' => [
                        'id' => $replyId,
                        'support_ticket_id' => $ticket->id,
                        'user_id' => $userId,
                        'sender_type' => 'seller',
                        'message' => $data['message'] ?? '',
                        'image' => $imagePath,
                        'image_url' => $imagePath ? Storage::disk('public')->url($imagePath) : null,
                        'created_at' => $now,
                    ],
                ];
            }

            // First message: create the ticket.
            $ticketId = DB::table('support_tickets')
                ->insertGetId([
                    'user_id' => $userId,
                    'complaint_type_id' => $data['complaint_type_id'] ?? null,
                    'priority_id' => $data['priority_id'] ?? null,
                    'subject' => $data['subject'],
                    'message' => $data['message'] ?? '',
                    'image' => $imagePath,
                    'status_id' => null,
                    'last_reply_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            $replyId = DB::table('support_ticket_replies')
                ->insertGetId([
                    'support_ticket_id' => $ticketId,
                    'user_id' => $userId,
                    'sender_type' => 'seller',
                    'message' => $data['message'] ?? '',
                    'image' => $imagePath,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            return [
                'message' => 'Support ticket created successfully.',
                'ticket_id' => $ticketId,
                'reply_id' => $replyId,
                'image_url' => $imagePath ? Storage::disk('public')->url($imagePath) : null,
            ];
        });
    }

    public function getMyTickets()
    {
        $userId = auth()->id();

        if (!$userId) {
            abort(401, 'Please login first.');
        }

        return DB::table('support_tickets')
            ->leftJoin('master_types as status', 'status.id', '=', 'support_tickets.status_id')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'support_tickets.user_id')
            ->leftJoin('support_ticket_replies as latest_reply', function ($join) {
                $join->on('latest_reply.id', '=', DB::raw('(
                    SELECT r.id
                    FROM support_ticket_replies r
                    WHERE r.support_ticket_id = support_tickets.id
                    ORDER BY r.created_at DESC, r.id DESC
                    LIMIT 1
                )'));
            })
            ->where(function ($query) use ($userId) {
                $query
                    // Existing admin-support tickets
                    ->where(function ($q) use ($userId) {
                        $q->where('support_tickets.user_id', $userId)
                        ->where(function ($sub) {
                            $sub->where('support_tickets.conversation_type', 'support')
                                ->orWhereNull('support_tickets.conversation_type');
                        });
                    })
                    // Buyer chats belonging to this seller
                    ->orWhere(function ($q) use ($userId) {
                        $q->where('support_tickets.seller_id', $userId)
                        ->where('support_tickets.conversation_type', 'buyer_seller');
                    });
            })
            ->select(
                'support_tickets.id',
                'support_tickets.user_id',
                'support_tickets.seller_id',
                'support_tickets.conversation_type',
                'support_tickets.subject',
                'support_tickets.message',
                'support_tickets.image',
                'support_tickets.status_id',
                'status.description as status_name',
                'support_tickets.created_at',
                'support_tickets.last_reply_at',
                'buyer.name as buyer_name',
                'buyer.email as buyer_email',
                'latest_reply.message as latest_message',
                'latest_reply.sender_type as latest_sender_type',
                'latest_reply.user_id as latest_sender_id'
            )
            ->orderByRaw(
                'COALESCE(latest_reply.created_at, support_tickets.last_reply_at, support_tickets.created_at) DESC'
            )
            ->orderByDesc('support_tickets.id')
            ->get()
            ->map(function ($ticket) {
                $ticket->image_url = $ticket->image
                    ? Storage::disk('public')->url($ticket->image)
                    : null;

                return $ticket;
            });
    }

    public function getMyTicketById(int $id): array
    {
        $userId = auth()->id();

        if (!$userId) {
            abort(401, 'Please login first.');
        }

        $ticket = DB::table('support_tickets')
            ->leftJoin('users as buyer', 'buyer.id', '=', 'support_tickets.user_id')
            ->where('support_tickets.id', $id)
            ->where(function ($query) use ($userId) {
                $query
                    // Existing admin-support ticket
                    ->where(function ($q) use ($userId) {
                        $q->where('support_tickets.user_id', $userId)
                        ->where(function ($sub) {
                            $sub->where('support_tickets.conversation_type', 'support')
                                ->orWhereNull('support_tickets.conversation_type');
                        });
                    })
                    // Buyer chat: only this seller can open it
                    ->orWhere(function ($q) use ($userId) {
                        $q->where('support_tickets.seller_id', $userId)
                        ->where('support_tickets.conversation_type', 'buyer_seller');
                    });
            })
            ->select(
                'support_tickets.id',
                'support_tickets.user_id',
                'support_tickets.seller_id',
                'support_tickets.conversation_type',
                'support_tickets.subject',
                'support_tickets.message',
                'support_tickets.last_reply_at',
                'support_tickets.image',
                // 'support_tickets.created_at',
                // 'support_tickets.updated_at',
                'buyer.name as buyer_name',
                // 'buyer.email as buyer_email'
            )
            ->first();

        abort_if(!$ticket, 404, 'Support ticket not found.');

        $ticket->image_url = $ticket->image
            ? Storage::disk('public')->url($ticket->image)
            : null;

        $replies = DB::table('support_ticket_replies as r')
            ->leftJoin('users as sender', 'sender.id', '=', 'r.user_id')
            ->where('r.support_ticket_id', $id)
            ->select(
                'r.id',
                'r.support_ticket_id',
                'r.user_id',
                'r.sender_type',
                'r.message',
                'r.image',
                // 'r.created_at',
                // 'r.updated_at',
                'sender.name as sender_name',
                // 'sender.email as sender_email'
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

        $buyerReplies = $replies
            ->where('sender_type', 'buyer')
            ->values();

        $sellerReplies = $replies
            ->where('sender_type', 'seller')
            ->values();

        return [
            'ticket' => $ticket,
            'buyer_replies' => $buyerReplies,
            'seller_replies' => $sellerReplies,
        ];
    }

}
