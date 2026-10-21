<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AdminDashboardService
{
    private const PROCESSING = 46;
    private const SHIPPING = 47;
    private const DELIVERED = 48;
    private const CANCELLED = 49;

    public function getDashboard(int $months = 6): array
    {
        $months = in_array($months, [3, 6, 12], true)
            ? $months
            : 6;

        $now = Carbon::now();
        $start = $now->copy()->startOfMonth()
            ->subMonths($months - 1);

        $previousStart = $start->copy()->subMonths($months);
        $previousEnd = $start->copy()->subSecond();

        $current = $this->salesSummary($start, $now);
        $previous = $this->salesSummary(
            $previousStart,
            $previousEnd
        );

        $earnings = $this->earningSummary($start, $now);

        return [
            'summary' => [
                'total_sales' => $current['sales'],
                'total_orders' => $current['orders'],
                'platform_profit' => $earnings['profit'],
                'average_order' => $current['average_order'],

                'sales_change' => $this->percentageChange(
                    $current['sales'],
                    $previous['sales']
                ),
                'orders_change' => $this->percentageChange(
                    $current['orders'],
                    $previous['orders']
                ),
                'profit_change' => $this->percentageChange(
                    $earnings['profit'],
                    $this->earningSummary(
                        $previousStart,
                        $previousEnd
                    )['profit']
                ),
                'average_order_change' =>
                    $this->percentageChange(
                        $current['average_order'],
                        $previous['average_order']
                    ),

                'period' => [
                    'months' => $months,
                    'from' => $start->toDateString(),
                    'to' => $now->toDateString(),
                ],
            ],

            'earning_insights' => [
                'revenue' => $current['sales'],
                'profit' => $earnings['profit'],
                'refunded' => $this->refundedAmount(
                    $start,
                    $now
                ),
                'revenue_change' => $this->percentageChange(
                    $current['sales'],
                    $previous['sales']
                ),
                'monthly' => $this->monthlyRevenue(
                    $start,
                    $now
                ),
            ],

            'fulfillment' => $this->fulfillment(),

            'deliveries_in_progress' =>
                $this->deliveriesInProgress(),

            'popular_products' =>
                $this->popularProducts(),
        ];
    }

    private function salesSummary($start, $end): array
    {
        $query = DB::table('orders')
            ->whereBetween('created_at', [$start, $end])
            ->where('status_id', '!=', self::CANCELLED);

        $sales = (float) (clone $query)->sum('total_amount');
        $count = (int) (clone $query)->count();

        return [
            'sales' => round($sales, 2),
            'orders' => $count,
            'average_order' => $count > 0
                ? round($sales / $count, 2)
                : 0,
        ];
    }

    private function earningSummary($start, $end): array
    {
        $profit = DB::table('seller_earnings')
            ->whereBetween('created_at', [$start, $end])
            ->sum('commission_amount');

        return [
            'profit' => round((float) $profit, 2),
        ];
    }

    private function percentageChange(
        float|int $current,
        float|int $previous
    ): array {
        if ((float) $previous == 0.0) {
            return [
                'percentage' => null,
                'direction' => 'no_previous_data',
            ];
        }

        $change = round(
            (($current - $previous) / abs($previous)) * 100,
            1
        );

        return [
            'percentage' => abs($change),
            'direction' => $change < 0
                ? 'decrease'
                : ($change > 0 ? 'increase' : 'unchanged'),
        ];
    }

    private function monthlyRevenue($start, $end): array
    {
        $rows = DB::table('orders')
            ->selectRaw(
                "DATE_TRUNC('month', created_at) AS month,
                 COALESCE(SUM(total_amount), 0) AS revenue"
            )
            ->whereBetween('created_at', [$start, $end])
            ->where('status_id', '!=', self::CANCELLED)
            ->groupByRaw("DATE_TRUNC('month', created_at)")
            ->orderByRaw("DATE_TRUNC('month', created_at)")
            ->get()
            ->keyBy(fn ($row) =>
                Carbon::parse($row->month)->format('Y-m')
            );

        $result = [];

        $cursor = $start->copy()->startOfMonth();
        $last = $end->copy()->startOfMonth();

        while ($cursor <= $last) {
            $key = $cursor->format('Y-m');

            $result[] = [
                'month' => $key,
                'label' => $cursor->format('M'),
                'revenue' => round(
                    (float) ($rows[$key]->revenue ?? 0),
                    2
                ),
            ];

            $cursor->addMonth();
        }

        return $result;
    }

    private function fulfillment(): array
    {
        $counts = DB::table('orders')
            ->select('status_id')
            ->selectRaw('COUNT(*) AS total')
            ->whereIn('status_id', [
                self::PROCESSING,
                self::SHIPPING,
                self::DELIVERED,
            ])
            ->groupBy('status_id')
            ->pluck('total', 'status_id');

        $processing = (int) ($counts[self::PROCESSING] ?? 0);
        $shipping = (int) ($counts[self::SHIPPING] ?? 0);
        $delivered = (int) ($counts[self::DELIVERED] ?? 0);

        return [
            'total' => $processing + $shipping + $delivered,
            'pending_processing' => $processing,
            'shipped' => $shipping,
            'delivered' => $delivered,
        ];
    }

    private function refundedAmount($start, $end): float
    {
        return round(
            (float) DB::table('refunds')
                ->whereNotNull('refunded_at')
                ->whereBetween('refunded_at', [$start, $end])
                ->sum('refunded_amount'),
            2
        );
    }

    private function deliveriesInProgress(): array
    {
        // Latest address record per order.
        $latestAddresses = DB::table('order_addresses')
            ->selectRaw('order_id, MAX(id) AS address_id')
            ->groupBy('order_id');

        return DB::table('orders as o')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('master_types as mt', 'mt.id', '=', 'o.status_id')
            ->leftJoinSub(
                $latestAddresses,
                'latest_address',
                fn ($join) => $join->on('latest_address.order_id', '=', 'o.id')
            )
            ->leftJoin(
                'order_addresses as oa',
                'oa.id',
                '=',
                'latest_address.address_id'
            )
            ->whereIn('o.status_id', [
                self::PROCESSING,
                self::SHIPPING,
            ])
            ->select([
                'o.id',
                'o.order_number',
                'o.total_amount',
                'o.created_at',
                'o.status_id',
                'mt.value as status',
                'mt.description as status_description',
                'u.name as customer_name',
                'oa.first_name',
                'oa.last_name',
                'oa.address_line_1',
                'oa.address_line_2',
                'oa.city',
                'oa.state',
                'oa.postal_code',
                'oa.country',
            ])
            ->orderByDesc('o.created_at')
            ->limit(15)
            ->get()
            ->map(function ($order) {
                $items = DB::table('order_items')
                    ->where('order_id', $order->id)
                    ->select('product_name', 'quantity')
                    ->get();

                $addressParts = array_filter([
                    $order->address_line_1,
                    $order->address_line_2,
                    $order->city,
                    $order->state,
                    $order->postal_code,
                    $order->country,
                ], fn ($value) => filled($value));

                $customerName = trim(
                    ($order->first_name ?? '') . ' ' .
                    ($order->last_name ?? '')
                );

                return [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $customerName
                        ?: $order->customer_name,
                    'amount' => (float) $order->total_amount,
                    'status_id' => $order->status_id,
                    'status' => $order->status_description
                        ?: $order->status,
                    'date' => $order->created_at,
                    'address' => implode(', ', $addressParts),
                    'items' => $items,
                ];
            })
            ->all();
    }

    private function popularProducts(): array
    {
        $reviewCounts = DB::table('reviews')
            ->select('product_id')
            ->selectRaw('COUNT(*) AS review_count')
            ->where('is_approved', true)
            ->groupBy('product_id');

        return DB::table('order_items as oi')
            ->join('products as p', 'p.id', '=', 'oi.product_id')
            ->leftJoinSub(
                $reviewCounts,
                'rc',
                fn ($join) => $join->on('rc.product_id', '=', 'p.id')
            )
            ->select([
                'p.id',
                'p.name',
                'p.price',
                'p.product_code',
            ])
            ->selectRaw('COALESCE(SUM(oi.quantity), 0) AS sold_quantity')
            ->selectRaw('COALESCE(SUM(oi.total_amount), 0) AS sales')
            ->selectRaw('COALESCE(MAX(rc.review_count), 0) AS reviews')
            ->groupBy(
                'p.id',
                'p.name',
                'p.price',
                'p.product_code'
            )
            ->orderByDesc('sold_quantity')
            ->limit(5)
            ->get()
            ->map(function ($product) {
                // products.price is VARCHAR, so cast only valid numbers.
                $price = is_numeric($product->price)
                    ? (float) $product->price
                    : 0;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'product_code' => $product->product_code,
                    'price' => $price,
                    'sold_quantity' => (int) $product->sold_quantity,
                    'sales' => (float) $product->sales,
                    'reviews' => (int) $product->reviews,
                    'image' => null,
                ];
            })
            ->all();
    }
}
