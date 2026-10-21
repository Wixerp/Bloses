<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureSellerIsNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $seller = DB::table('sellers')
            ->where('user_id', $user->id)
            ->first();

        if (!$seller) {
            return response()->json([
                'success' => false,
                'message' => 'Seller profile not found.',
            ], 403);
        }

        $suspendedStatusId = DB::table('master_types')
            ->where('key', 'seller_status')
            ->where('value', 'Suspended')
            ->where('is_active', true)
            ->value('id');

        if (
            $suspendedStatusId &&
            (int) $seller->status_id === (int) $suspendedStatusId
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Your seller account is suspended.',
                'code' => 'SELLER_SUSPENDED',
            ], 403);
        }

        return $next($request);
    }
}
