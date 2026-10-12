<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('chat.{ticketId}', function ($user, $ticketId) {
    if (!$user) {
        return false;
    }

    $ticket = DB::table('support_tickets')->where('id', $ticketId)->first();

    if (!$ticket) {
        return false;
    }

    if ($ticket->conversation_type === 'buyer_seller') {
        if ((int) $ticket->user_id === (int) $user->id) {
            return true;
        }

        if ((int) $ticket->seller_id === (int) $user->id) {
            return true;
        }
    }

    if ($ticket->conversation_type === 'support') {
        if ((int) $ticket->user_id === (int) $user->id) {
            return true;
        }

        if (
            !empty($ticket->assigned_admin) &&
            (int) $ticket->assigned_admin === (int) $user->id
        ) {
            return true;
        }
    }

    return DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $user->id)
        ->where('model_has_roles.model_type', get_class($user))
        ->where('roles.name', 'admin')->exists();
});
