
<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('chat.{ticketId}', function ($user, $ticketId) {
    $ticket = DB::table('support_tickets')->where('id', $ticketId)->where('conversation_type', 'buyer_seller')->first();
    if (!$ticket) {
        return false;
    }
    if ((int) $ticket->user_id === (int) $user->id) {
        return true;
    }
    if ((int) $ticket->seller_id === (int) $user->id) {
        return true;
    }
    return false;
});
