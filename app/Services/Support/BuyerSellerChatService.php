<?php

namespace App\Services\Support;

use App\Events\ChatMessageSent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BuyerSellerChatService
{
    private function getAuthorizedChat(int $orderId, int $sellerId): array
    {
        $buyerId = auth()->id();
        abort_if(!$buyerId, 401, 'Please login first.');

        $order = DB::table('orders')->where('id', $orderId)->where('user_id', $buyerId)->first();
        abort_if(!$order, 404, 'Order not found.');

        $orderHasSeller = DB::table('order_items')->where('order_id', $orderId)->where('seller_id', $sellerId)->exists();
        abort_if(!$orderHasSeller, 403, 'Seller does not belong to this order.');

        $seller = DB::table('sellers')->where('id', $sellerId)->first();
        abort_if(!$seller || !$seller->user_id, 404, 'Seller not found.');

        return [(int) $buyerId, (int) $seller->user_id];
    }

    public function openChat(int $orderId): array
    {
        $buyerId = auth()->id();
        abort_if(!$buyerId, 401, 'Please login first.');

        $order = DB::table('orders')->where('id', $orderId)->where('user_id', $buyerId)->first();
        abort_if(!$order, 404, 'Order not found.');

        $sellerIds = DB::table('order_items')->where('order_id', $orderId)->distinct()->pluck('seller_id');
        abort_if($sellerIds->count() !== 1, 422, 'This order must contain products from exactly one seller.');

        $seller = DB::table('sellers')->where('id', $sellerIds->first())->first();
        abort_if(!$seller, 404, 'Seller not found.');

        $ticket = DB::table('support_tickets')
            ->where('user_id', $buyerId)->where('seller_id', $seller->user_id)
            ->where('conversation_type', 'buyer_seller')
            ->where('id', function ($query) use ($orderId) {
                $query->select('support_tickets.id')
                    ->from('support_tickets')
                    ->whereColumn('support_tickets.user_id', '=', DB::raw((int) auth()->id()))
                    ->where('support_tickets.conversation_type', 'buyer_seller')
                    ->orderByDesc('support_tickets.id')
                    ->limit(1);
            })->first();

        if (!$ticket) {
            return [
                'ticket' => null,
                'replies' => [],
            ];
        }

        return [
            'ticket' => $ticket,
            'replies' => $this->getReplies((int) $ticket->id),
        ];
    }


    public function sendMessage(int $orderId, array $data): array
    {
        $buyerId = auth()->id();
        abort_if(!$buyerId, 401, 'Please login first.');

        $order = DB::table('orders')->where('id', $orderId)->where('user_id', $buyerId)->first();
        abort_if(!$order, 404, 'Order not found.');

        $sellerIds = DB::table('order_items')->where('order_id', $orderId)->whereNotNull('seller_id')->distinct()->pluck('seller_id');
        abort_if($sellerIds->count() !== 1, 422, 'This order must contain products from exactly one seller.');

        $sellerId = (int) $sellerIds->first();
        $seller = DB::table('sellers')->where('id', $sellerId)->first();
        abort_if(!$seller || !$seller->user_id, 404, 'Seller not found.');

        $sellerUserId = (int) $seller->user_id;
        $message = trim($data['message'] ?? '');
        $image = $data['image'] ?? null;
        abort_if($message === '' && !($image instanceof UploadedFile), 422, 'Message or image is required.');

        $imagePath = null;

        if ($image instanceof UploadedFile) {
            $imagePath = $image->store('buyer-seller-chat', 'public');
            abort_if(!$imagePath, 500, 'Unable to upload chat image.');
        }

        try {
            $result = DB::transaction(function () use ($buyerId, $sellerUserId, $message, $imagePath)
            {
                $now = now();

                $ticket = DB::table('support_tickets')->where('user_id', $buyerId)->where('seller_id', $sellerUserId)
                    ->where('conversation_type', 'buyer_seller')->lockForUpdate()->first();

                if (!$ticket) {
                    $ticketId = DB::table('support_tickets')
                        ->insertGetId([
                            'user_id' => $buyerId,
                            'seller_id' => $sellerUserId,
                            'conversation_type' => 'buyer_seller',
                            'subject' => 'Buyer-Seller Chat',
                            'message' => $message,
                            'image' => $imagePath,
                            'status_id' => null,
                            'last_reply_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                } else {
                    $ticketId = (int) $ticket->id;

                    DB::table('support_tickets')->where('id', $ticketId)
                        ->update([
                            'message' => $message,
                            'image' => $imagePath,
                            'last_reply_at' => $now,
                            'updated_at' => $now,
                        ]);
                }

                $replyId = DB::table('support_ticket_replies')
                    ->insertGetId([
                        'support_ticket_id' => $ticketId,
                        'user_id' => $buyerId,
                        'sender_type' => 'buyer',
                        'message' => $message,
                        'image' => $imagePath,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                return [
                    'ticket_id' => (int) $ticketId,
                    'reply_id' => (int) $replyId,
                    'user_id' => (int) $buyerId,
                    'message' => $message,
                    'image_path' => $imagePath,
                    'created_at' => $now,
                ];
            });
        } catch (Throwable $e) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            throw $e;
        }

        $reply = [
            'id' => $result['reply_id'],
            'support_ticket_id' => $result['ticket_id'],
            'user_id' => $result['user_id'],
            'sender_type' => 'buyer',
            'message' => $result['message'],
            'image_url' => $result['image_path'] ? Storage::disk('public')->url($result['image_path']) : null,
            'created_at' => $result['created_at']->toISOString(),
        ];

        event(new ChatMessageSent($result['ticket_id'], $reply));

        return [
            'success' => true,
            'message' => 'Message sent successfully.',
            'ticket_id' => $result['ticket_id'],
            'reply' => $reply,
        ];
    }

    public function getSellerChats(): array
    {
        $sellerUserId = auth()->id();
        abort_if(!$sellerUserId, 401, 'Please login first.');

        return DB::table('support_tickets as t')
            ->leftJoin('users as buyer', 'buyer.id', '=', 't.user_id')
            ->where('t.seller_id', $sellerUserId)
            ->where('t.conversation_type', 'buyer_seller')
            ->select(
                't.id as ticket_id',
                't.user_id as buyer_id',
                'buyer.name as buyer_name',
                't.subject',
                't.last_reply_at',
                't.created_at'
            )
            ->orderByRaw('t.last_reply_at DESC NULLS LAST')
            ->orderByDesc('t.id')
            ->get()
            ->map(function ($ticket) {
                $lastReply = DB::table('support_ticket_replies')
                    ->where('support_ticket_id', $ticket->ticket_id)
                    ->orderByDesc('id')
                    ->first();

                $ticket->last_message = $lastReply?->message;
                $ticket->last_sender_type = $lastReply?->sender_type;
                $ticket->last_message_at = $lastReply?->created_at;

                return $ticket;
            })->all();
    }

    public function getSellerChat(int $ticketId): array
    {
        $sellerUserId = auth()->id();

        abort_if(!$sellerUserId, 401, 'Please login first.');

        $ticket = DB::table('support_tickets as t')
            ->leftJoin('users as buyer', 'buyer.id', '=', 't.user_id')
            ->where('t.id', $ticketId)
            ->where('t.seller_id', $sellerUserId)
            ->where('t.conversation_type', 'buyer_seller')
            ->select('t.*', 'buyer.name as buyer_name')
            ->first();

        abort_if(!$ticket, 404, 'Conversation not found.');

        return [
            'ticket' => $ticket,
            'replies' => $this->getReplies($ticketId),
        ];
    }

    public function sellerSendMessage(int $ticketId, array $data): array
    {
        $sellerUserId = auth()->id();

        abort_if(!$sellerUserId, 401, 'Please login first.');

        $message = trim($data['message'] ?? '');
        $image = $data['image'] ?? null;

        abort_if(
            $message === '' && !($image instanceof UploadedFile),
            422,
            'Message or image is required.'
        );

        $ticket = DB::table('support_tickets')
            ->where('id', $ticketId)
            ->where('seller_id', $sellerUserId)
            ->where('conversation_type', 'buyer_seller')
            ->first();

        abort_if(!$ticket, 404, 'Conversation not found.');

        $imagePath = null;

        if ($image instanceof UploadedFile) {
            $imagePath = $image->store('buyer-seller-chat', 'public');

            abort_if(!$imagePath, 500, 'Unable to upload chat image.');
        }

        try {
            $result = DB::transaction(function () use (
                $ticketId,
                $sellerUserId,
                $message,
                $imagePath
            ) {
                $ownedTicket = DB::table('support_tickets')
                    ->where('id', $ticketId)
                    ->where('seller_id', $sellerUserId)
                    ->where('conversation_type', 'buyer_seller')
                    ->lockForUpdate()
                    ->first();

                abort_if(!$ownedTicket, 404, 'Conversation not found.');

                $now = now();

                $replyId = DB::table('support_ticket_replies')
                    ->insertGetId([
                        'support_ticket_id' => $ticketId,
                        'user_id' => $sellerUserId,
                        'sender_type' => 'seller',
                        'message' => $message,
                        'image' => $imagePath,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                DB::table('support_tickets')
                    ->where('id', $ticketId)
                    ->update([
                        'last_reply_at' => $now,
                        'updated_at' => $now,
                    ]);

                return [
                    'ticket_id' => $ticketId,
                    'reply_id' => (int) $replyId,
                    'user_id' => (int) $sellerUserId,
                    'message' => $message,
                    'image_path' => $imagePath,
                    'created_at' => $now,
                ];
            });
        } catch (Throwable $e) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $e;
        }

        $reply = [
            'id' => $result['reply_id'],
            'support_ticket_id' => $result['ticket_id'],
            'user_id' => $result['user_id'],
            'sender_type' => 'seller',
            'message' => $result['message'],
            'image_url' => $result['image_path']
                ? Storage::disk('public')->url($result['image_path'])
                : null,
            'created_at' => $result['created_at']->toISOString(),
        ];

        event(new ChatMessageSent($result['ticket_id'], $reply));

        return [
            'success' => true,
            'message' => 'Reply sent successfully.',
            'ticket_id' => $result['ticket_id'],
            'reply' => $reply,
        ];
    }

    private function getReplies(int $ticketId)
    {
        return DB::table('support_ticket_replies as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.support_ticket_id', $ticketId)
            ->select(
                'r.id',
                'r.support_ticket_id',
                'r.user_id',
                'r.sender_type',
                'r.message',
                'r.image',
                'r.created_at',
                'u.name as sender_name'
            )->orderBy('r.created_at')->orderBy('r.id')->get()
            ->map(function ($reply) {
                $reply->image_url = $reply->image ? Storage::disk('public')->url($reply->image) : null;

                return $reply;
            });
    }
}
