<?php

namespace App\Services\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SupportService
{
    // public function createTicket(array $data): array
    // {
    //     $userId = auth()->id();

    //     if (!$userId) {
    //         abort(401, 'Please login first.');
    //     }

    //     return DB::transaction(function () use ($data, $userId) {
    //         $now = now();

    //         // Existing ticket_id diya ho to wahi ticket use karo.
    //         $ticket = null;

    //         if (!empty($data['ticket_id'])) {
    //             $ticket = DB::table('support_tickets')
    //                 ->where('id', $data['ticket_id'])
    //                 ->where('user_id', $userId)
    //                 ->first();

    //             abort_if(!$ticket, 404, 'Support ticket not found.');
    //         } else {
    //             // ticket_id nahi hai to seller ka existing ticket check karo.
    //             $ticket = DB::table('support_tickets')
    //                 ->where('user_id', $userId)
    //                 ->orderByDesc('id')
    //                 ->first();
    //         }

    //         // Existing ticket: message sirf replies table mein save hoga.
    //         if ($ticket) {
    //             $replyId = DB::table('support_ticket_replies')
    //                 ->insertGetId([
    //                     'support_ticket_id' => $ticket->id,
    //                     'user_id' => $userId,
    //                     'sender_type' => 'seller',
    //                     'message' => $data['message'],
    //                     'created_at' => $now,
    //                     'updated_at' => $now,
    //                 ]);

    //             DB::table('support_tickets')
    //                 ->where('id', $ticket->id)
    //                 ->update([
    //                     'last_reply_at' => $now,
    //                     'updated_at' => $now,
    //                 ]);

    //             return [
    //                 'message' => 'Reply sent successfully.',
    //                 'ticket_id' => $ticket->id,
    //                 'reply_id' => $replyId,
    //             ];
    //         }

    //         // First message only: create the ticket.
    //         $imagePath = null;

    //         if (
    //             isset($data['image']) &&
    //             $data['image'] instanceof UploadedFile
    //         ) {
    //             $imagePath = $data['image']->store(
    //                 'support-tickets',
    //                 'public'
    //             );
    //         }

    //         $ticketId = DB::table('support_tickets')
    //             ->insertGetId([
    //                 'user_id' => $userId,
    //                 'complaint_type_id' => $data['complaint_type_id'] ?? null,
    //                 'priority_id' => $data['priority_id'] ?? null,
    //                 'subject' => $data['subject'],
    //                 'message' => $data['message'],
    //                 'image' => $imagePath,
    //                 'status_id' => null,
    //                 'last_reply_at' => $now,
    //                 'created_at' => $now,
    //                 'updated_at' => $now,
    //             ]);

    //         $replyId = DB::table('support_ticket_replies')
    //             ->insertGetId([
    //                 'support_ticket_id' => $ticketId,
    //                 'user_id' => $userId,
    //                 'sender_type' => 'seller',
    //                 'message' => $data['message'],
    //                 'created_at' => $now,
    //                 'updated_at' => $now,
    //             ]);

    //         return [
    //             'message' => 'Support ticket created successfully.',
    //             'ticket_id' => $ticketId,
    //             'reply_id' => $replyId,
    //         ];
    //     });
    // }

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
                $ticket = DB::table('support_tickets')->where('id', $data['ticket_id'])->where('user_id', $userId)->first();

                abort_if(!$ticket, 404, 'Support ticket not found.');
            } else {
                $ticket = DB::table('support_tickets')->where('user_id', $userId)->orderByDesc('id')->first();
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
            ->where('support_tickets.user_id', $userId)
            ->leftJoin('master_types as status', 'status.id', '=', 'support_tickets.status_id')
            ->leftJoin('support_ticket_replies as latest_reply', function ($join) {
                $join->on('latest_reply.id', '=',
                    DB::raw('(
                        SELECT r.id FROM support_ticket_replies r
                        WHERE r.support_ticket_id = support_tickets.id
                        ORDER BY r.created_at DESC, r.id DESC
                        LIMIT 1
                    )')
                );
            })
            ->select(
                'support_tickets.id','support_tickets.user_id','support_tickets.subject','support_tickets.message','support_tickets.image',
                'support_tickets.status_id','status.description as status_name','support_tickets.created_at','support_tickets.last_reply_at',
                'latest_reply.message as latest_message','latest_reply.sender_type as latest_sender_type','latest_reply.user_id as latest_sender_id'
            )
            ->orderByRaw('COALESCE(latest_reply.created_at, support_tickets.last_reply_at, support_tickets.created_at ) DESC')
            ->orderByDesc('support_tickets.id')->get()
            ->map(function ($ticket) {
                $ticket->image_url = $ticket->image ? Storage::disk('public')->url($ticket->image) : null;

                return $ticket;
            });
    }

    // public function getMyTicketById(int $id): array
    // {
    //     $userId = auth()->id();

    //     if (!$userId) {
    //         abort(401, 'Please login first.');
    //     }

    //     $ticket = DB::table('support_tickets')
    //         ->where('support_tickets.id', $id)
    //         ->where('support_tickets.user_id', $userId)
    //         ->select(
    //             'id',
    //             'user_id',
    //             'subject',
    //             'message',
    //             'last_reply_at',
    //             'image'
    //         )
    //         ->first();

    //     abort_if(!$ticket, 404, 'Support ticket not found.');

    //     $ticket->image_url = $ticket->image ? Storage::disk('public')->url($ticket->image) : null;

    //     $replies = DB::table('support_ticket_replies as r')
    //         ->leftJoin('users as sender', 'sender.id', '=', 'r.user_id')
    //         ->where('r.support_ticket_id', $id)
    //         ->select(
    //             'r.id',
    //             'r.support_ticket_id',
    //             'r.user_id',
    //             'r.sender_type',
    //             'r.message',
    //             'sender.name as sender_name',
    //         )
    //         ->orderBy('r.created_at')
    //         ->orderBy('r.id')
    //         ->get();

    //     return [
    //         'ticket' => $ticket,
    //         'replies' => $replies,
    //     ];
    // }

    public function getMyTicketById(int $id): array
    {
        $userId = auth()->id();

        if (!$userId) {
            abort(401, 'Please login first.');
        }

        $ticket = DB::table('support_tickets')
            ->where('support_tickets.id', $id)
            ->where('support_tickets.user_id', $userId)
            ->select(
                'id',
                'user_id',
                'subject',
                'message',
                'last_reply_at',
                'image',
                'created_at',
                'updated_at'
            )
            ->first();

        abort_if(!$ticket, 404, 'Support ticket not found.');

        // Ticket image URL
        $ticket->image_url = $ticket->image
            ? Storage::disk('public')->url($ticket->image)
            : null;

        // Ticket replies with sender details and images
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
            'ticket' => $ticket,
            'replies' => $replies,
        ];
    }

}
