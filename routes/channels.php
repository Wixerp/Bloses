<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

// Broadcast::channel('chat.{ticketId}', function ($user, $ticketId) {
//     $ticket = DB::table('support_tickets')
//         ->where('id', $ticketId)
//         ->first();

//     if (!$ticket) {
//         return false;
//     }

//     // Buyer-Seller Chat
//     if ($ticket->conversation_type === 'buyer_seller') {
//         return (int) $ticket->user_id === (int) $user->id
//             || (int) $ticket->seller_id === (int) $user->id;
//     }

//     // Seller Support: ticket owner and assigned admin
//     if ($ticket->conversation_type === 'support') {
//         return (int) $ticket->user_id === (int) $user->id
//             || (
//                 !empty($ticket->assigned_admin)
//                 && (int) $ticket->assigned_admin === (int) $user->id
//             );
//     }

//     return false;
// });

// Broadcast::channel('chat.{ticketId}', function ($user, $ticketId) {
//     if (!$user) {
//         return false;
//     }

//     $ticket = DB::table('support_tickets')
//         ->where('id', $ticketId)
//         ->where('conversation_type', 'buyer_seller')
//         ->first();

//     if (!$ticket) {
//         return false;
//     }

//     // Buyer check
//     if ((int) $ticket->user_id === (int) $user->id) {
//         return true;
//     }

//     // Seller check — sellers.id se user_id resolve karo
//     if ($ticket->seller_id) {
//         $sellerUserId = DB::table('sellers')
//             ->where('id', $ticket->seller_id)
//             ->value('user_id');

//         if ($sellerUserId && (int) $sellerUserId === (int) $user->id) {
//             return true;
//         }
//     }

//     return false;
// });


// Broadcast::channel('chat.{ticketId}', function ($user, $ticketId) {
//     if (!$user) {
//         return false;
//     }

//     $ticket = DB::table('support_tickets')
//         ->where('id', $ticketId)
//         ->where('conversation_type', 'buyer_seller')
//         ->first();

//     if (!$ticket) {
//         return false;
//     }

//     // Buyer
//     if ((int) $ticket->user_id === (int) $user->id) {
//         return true;
//     }

//     // Seller: seller_id stores sellers.id
//     if ($ticket->seller_id) {
//         $sellerUserId = DB::table('sellers')
//             ->where('id', $ticket->seller_id)
//             ->value('user_id');

//         if (
//             $sellerUserId &&
//             (int) $sellerUserId === (int) $user->id
//         ) {
//             return true;
//         }
//     }

//     // Admin: only allow an authenticated admin user
//     $isAdmin = DB::table('model_has_roles')
//         ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
//         ->where('model_has_roles.model_id', $user->id)
//         ->where('model_has_roles.model_type', get_class($user))
//         ->where('roles.name', 'admin')
//         ->exists();

//     if ($isAdmin) {
//         return true;
//     }

//     return false;
// });

Broadcast::channel('chat.{ticketId}', function ($user, $ticketId) {
    if (!$user) {
        return false;
    }

    $ticket = DB::table('support_tickets')
        ->where('id', $ticketId)
        ->first();

    if (!$ticket) {
        return false;
    }

    // Buyer-Seller Chat
    if ($ticket->conversation_type === 'buyer_seller') {
        // Buyer
        if ((int) $ticket->user_id === (int) $user->id) {
            return true;
        }

        // Seller: seller_id stores users.id in this schema
        if ((int) $ticket->seller_id === (int) $user->id) {
            return true;
        }
    }

    // Seller Support: ticket owner
    if ($ticket->conversation_type === 'support') {
        if ((int) $ticket->user_id === (int) $user->id) {
            return true;
        }

        // Assigned admin
        if (
            !empty($ticket->assigned_admin) &&
            (int) $ticket->assigned_admin === (int) $user->id
        ) {
            return true;
        }
    }

    // Admin role check (Spatie)
    return DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $user->id)
        ->where('model_has_roles.model_type', get_class($user))
        ->where('roles.name', 'admin')
        ->exists();
});
