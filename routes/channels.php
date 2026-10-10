<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('chat.{ticketId}', function ($user, $ticketId) {
    $ticket = DB::table('support_tickets')
        ->where('id', $ticketId)
        ->where('conversation_type', 'buyer_seller')
        ->first();

    if (!$ticket) {
        return false;
    }

    // Buyer apni conversation access kar sakta hai.
    if ((int) $ticket->user_id === (int) $user->id) {
        return true;
    }

    // Seller ko bhi sirf apni conversation access ho.
    // Yahan seller_id ka users.id ya sellers.id hona confirm karna zaroori hai.
    return DB::table('sellers')
        ->where('id', $ticket->seller_id)
        ->where('user_id', $user->id)
        ->exists();
});
