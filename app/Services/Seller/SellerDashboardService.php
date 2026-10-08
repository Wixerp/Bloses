<?php

namespace App\Services\Seller;

use Illuminate\Support\Facades\DB;

class SellerDashboardService
{
    public function getDashboard(): array
    {
        $userId = auth()->id();

        if (!$userId) {
            abort(401, 'Please login first.');
        }

        $seller = DB::table('sellers')->where('user_id', $userId)->first();

        if (!$seller) {
            abort(404, 'Seller profile not found.');
        }

        $sellerId = $seller->id;
        $store = DB::table('seller_store_settings')->where('seller_id', $sellerId)->first();

        $storeData = $store
            ? [
                'id' => $store->id,
                'seller_id' => $store->seller_id,
                'store_name' => $store->store_name ?? null,
                'store_description' => $store->store_description ?? null,
                'store_logo' => $store->store_logo ?? null,
                'store_banner' => $store->store_banner ?? null,
                'rating' => $store->rating ?? 0,
                'verified' => $store->verified ?? false,
                'active' => $store->is_active ?? true,
            ] : null;

        $productQuery = DB::table('products')
            ->whereIn(
                'store_id',
                DB::table('seller_store_settings')
                    ->select('id')
                    ->where('seller_id', $sellerId)
            );

        $totalProducts = (clone $productQuery)->count();

        $lowStockProducts = (clone $productQuery)->where('qty', '>', 0)->where('qty', '<=', 5)
            ->orderBy('qty')->limit(10)
            ->get([
                'id','product_code','name','qty','price',
            ]);

        $lowStockCount = (clone $productQuery)->where('qty', '>', 0)->where('qty', '<=', 5)->count();
        $orderItemsQuery = DB::table('order_items')->where('order_items.seller_id', $sellerId);
        $totalOrders = (clone $orderItemsQuery)->distinct('order_id')->count('order_id');
        $pendingStatusIds = DB::table('master_types')->where('key', 'order_status')->where('value', 'pending')->pluck('id');

        $pendingOrders = DB::table('order_items')
            ->where('seller_id', $sellerId)
            ->whereIn(
                'order_id',
                DB::table('orders')
                    ->select('id')
                    ->whereIn('status_id', $pendingStatusIds)
            )->distinct('order_id')->count('order_id');

        $totalSales = DB::table('order_items')->where('seller_id', $sellerId)->sum('total_amount');
        $earnings = $totalSales;

        $salesChart = DB::table('order_items')
            ->join('orders','orders.id','=','order_items.order_id')
            ->where('order_items.seller_id', $sellerId)
            ->where('orders.created_at','>=',now()->subDays(6)->startOfDay())
            ->selectRaw("DATE(orders.created_at) as date,SUM(order_items.total_amount) as sales,COUNT(DISTINCT orders.id) as orders")
            ->groupByRaw('DATE(orders.created_at)')->orderBy('date')->get()
            ->map(function ($row) {
                return [
                    'date' => $row->date,
                    'sales' => (float) $row->sales,
                    'orders' => (int) $row->orders,
                ];
            })->values()->toArray();

        $orderStatus = DB::table('order_items')
            ->join('orders','orders.id','=','order_items.order_id')
            ->leftJoin('master_types as status','status.id','=','orders.status_id')
            ->where('order_items.seller_id', $sellerId)
            ->select('status.value','status.description')
            ->selectRaw('COUNT(DISTINCT orders.id) as total')
            ->groupBy('status.value','status.description')
            ->get()
            ->map(function ($row) {
                return [
                    'status' => $row->value,
                    'label' => $row->description ?? $row->value,
                    'count' => (int) $row->total,
                ];
            })->values()->toArray();

        $recentOrders = DB::table('orders')
            ->join('order_items','order_items.order_id','=','orders.id')
            ->leftJoin('master_types as status','status.id','=','orders.status_id')
            ->leftJoin('users as buyers','buyers.id','=','orders.user_id')
            ->where('order_items.seller_id', $sellerId)
            ->select('orders.id','orders.order_number','orders.created_at','status.value as status',
                'status.description as status_label','buyers.name as buyer_name')
            ->selectRaw('SUM(order_items.total_amount) as seller_total')
            ->selectRaw('COUNT(order_items.id) as items_count')
            ->groupBy('orders.id','orders.order_number','orders.created_at','status.value','status.description','buyers.name')
            ->orderByDesc('orders.created_at')->limit(10)->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'buyer_name' => $order->buyer_name,
                    'total' => (float) $order->seller_total,
                    'items_count' => (int) $order->items_count,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                    'created_at' => $order->created_at,
                ];
            })->values()->toArray();

        $topProducts = DB::table('order_items')
            ->join('products','products.id','=','order_items.product_id')
            ->where('order_items.seller_id', $sellerId)
            ->select(
                'products.id','products.product_code','products.name'
            )
            ->selectRaw('SUM(COALESCE(order_items.quantity, 0)) as sold_qty')
            ->selectRaw('SUM(COALESCE(order_items.total_amount, 0)) as sales')
            ->groupBy('products.id','products.product_code','products.name')->orderByDesc('sold_qty')->limit(5)->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'product_code' => $product->product_code,
                    'name' => $product->name,
                    'sold_qty' => (int) $product->sold_qty,
                    'sales' => (float) $product->sales,
                ];
            })->values()->toArray();

        $notifications = DB::table('notifications')
            ->leftJoin('master_types as notification_type','notification_type.id','=','notifications.type_id')
            ->where('notifications.user_id', $userId)
            ->select(
                'notifications.id','notifications.title','notifications.message','notifications.action_url',
                'notifications.is_read','notifications.read_at','notifications.created_at','notification_type.value as type',
                'notification_type.description as type_label'
            )->orderByDesc('notifications.created_at')->limit(10)->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'type_label' => $notification->type_label,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'action_url' => $notification->action_url,
                    'is_read' => (bool) $notification->is_read,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ];
            })->values()->toArray();

        return [
            'store' => $storeData,
            'summary' => [
                'total_sales' => (float) $totalSales,
                'total_orders' => (int) $totalOrders,
                'pending_orders' => (int) $pendingOrders,
                'total_products' => (int) $totalProducts,
                'low_stock' => (int) $lowStockCount,
                'earnings' => (float) $earnings,
            ],
            'sales_chart' => $salesChart,
            'order_status' => $orderStatus,
            'recent_orders' => $recentOrders,
            'top_products' => $topProducts,
            'low_stock_products' => $lowStockProducts
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'product_code' => $product->product_code,
                        'name' => $product->name,
                        'qty' => (int) $product->qty,
                        'price' => (float) $product->price,
                    ];
                })->values()->toArray(),

            'recent_returns' => [],
            'unanswered_questions' => [],
            'recent_payouts' => [],
            'notifications' => $notifications,
        ];
    }
}
