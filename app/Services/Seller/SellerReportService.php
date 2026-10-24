<?php

namespace App\Services\Seller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellerReportService
{
    private function sellerId(): int
    {
        $id = DB::table('sellers')
            ->where('user_id', auth()->id())
            ->value('id');

        if (!$id) {
            throw ValidationException::withMessages([
                'seller' => 'Seller profile not found.',
            ]);
        }

        return (int) $id;
    }

    private function dateRange(Request $request): array
    {
        $from = $request->input('from_date');
        $to = $request->input('to_date');

        if ($from && $to && $from > $to) {
            throw ValidationException::withMessages([
                'from_date' => 'from_date must be before or equal to to_date.',
            ]);
        }

        return [$from, $to];
    }

    private function applyDateRange($query, string $column, ?string $from, ?string $to)
    {
        if ($from) {
            $query->where($column, '>=', $from . ' 00:00:00');
        }

        if ($to) {
            $query->where($column, '<=', $to . ' 23:59:59.999999');
        }

        return $query;
    }

    private function salesItemsQuery(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('oi.seller_id', $this->sellerId());

        $this->applyDateRange(
            $query,
            'o.placed_at',
            $from,
            $to
        );

        return $query;
    }

    public function summary(Request $request): array
    {
        $items = $this->salesItemsQuery($request);

        $sales = (clone $items)
            ->selectRaw('
                COALESCE(SUM(oi.total_amount), 0) as gross_sales,
                COALESCE(SUM(oi.quantity), 0) as units_sold,
                COUNT(DISTINCT oi.order_id) as total_orders
            ')
            ->first();

        [$from, $to] = $this->dateRange($request);

        $earningsQuery = DB::table('seller_earnings')
            ->where('seller_id', $this->sellerId());

        $this->applyDateRange(
            $earningsQuery,
            'created_at',
            $from,
            $to
        );

        $earnings = $earningsQuery
            ->selectRaw('
                COALESCE(SUM(gross_amount), 0) as earnings_gross,
                COALESCE(SUM(commission_amount), 0) as commission_amount,
                COALESCE(SUM(net_amount), 0) as net_earnings
            ')
            ->first();

        return [
            'gross_sales' => (float) $sales->gross_sales,
            'units_sold' => (int) $sales->units_sold,
            'total_orders' => (int) $sales->total_orders,
            'earnings_gross' => (float) $earnings->earnings_gross,
            'commission_amount' => (float) $earnings->commission_amount,
            'net_earnings' => (float) $earnings->net_earnings,
        ];
    }

    public function salesByDate(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $query = $this->salesItemsQuery($request);

        return $query
            ->selectRaw("
                DATE(o.placed_at) as sale_date,
                COALESCE(SUM(oi.total_amount), 0) as sales,
                COALESCE(SUM(oi.quantity), 0) as units_sold,
                COUNT(DISTINCT oi.order_id) as total_orders
            ")
            ->whereNotNull('o.placed_at')
            ->groupByRaw('DATE(o.placed_at)')
            ->orderByRaw('DATE(o.placed_at) DESC')
            ->paginate(min(max((int) $request->input('per_page', 15), 1), 100));
    }

    public function orders(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('master_types as mt', 'mt.id', '=', 'o.status_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->where('oi.seller_id', $this->sellerId());

        $this->applyDateRange($query, 'o.placed_at', $from, $to);

        if ($request->filled('status_id')) {
            $query->where('o.status_id', $request->integer('status_id'));
        }

        return $query
            ->selectRaw('
                o.id as order_id,
                o.order_number,
                o.placed_at,
                o.status_id,
                mt.value as status,
                u.name as customer_name,
                COALESCE(SUM(oi.total_amount), 0) as seller_order_total,
                COALESCE(SUM(oi.quantity), 0) as item_quantity
            ')
            ->groupBy(
                'o.id',
                'o.order_number',
                'o.placed_at',
                'o.status_id',
                'mt.value',
                'u.name'
            )
            ->orderByDesc('o.placed_at')
            ->paginate(min(max((int) $request->input('per_page', 15), 1), 100));
    }

    public function bestSellingProducts(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
            ->where('oi.seller_id', $this->sellerId());

        $this->applyDateRange($query, 'o.placed_at', $from, $to);

        return $query
            ->selectRaw('
                oi.product_id,
                COALESCE(MAX(oi.product_name), MAX(p.name)) as product_name,
                COALESCE(SUM(oi.quantity), 0) as units_sold,
                COALESCE(SUM(oi.total_amount), 0) as sales_amount,
                COUNT(DISTINCT oi.order_id) as total_orders
            ')
            ->groupBy('oi.product_id')
            ->orderByDesc('units_sold')
            ->paginate(min(max((int) $request->input('per_page', 15), 1), 100));
    }

    public function inventory(Request $request)
    {
        $sellerId = $this->sellerId();

        $query = DB::table('products as p')
            ->join('seller_store_settings as st', 'st.id', '=', 'p.store_id')
            ->where('st.seller_id', $sellerId)
            ->selectRaw("
                p.id as product_id,
                p.name as product_name,
                p.product_code,
                p.status_id,
                p.is_active,
                CASE
                    WHEN NULLIF(BTRIM(p.qty), '') ~ '^[0-9]+([.][0-9]+)?$'
                    THEN BTRIM(p.qty)::numeric
                    ELSE 0
                END as stock_quantity,
                CASE
                    WHEN NULLIF(BTRIM(p.low_stock_threshold), '') ~ '^[0-9]+([.][0-9]+)?$'
                    THEN BTRIM(p.low_stock_threshold)::numeric
                    ELSE 5
                END as low_stock_threshold
            ");

        if ($request->boolean('low_stock_only')) {
            $query->whereRaw("
                CASE
                    WHEN NULLIF(BTRIM(p.qty), '') ~ '^[0-9]+([.][0-9]+)?$'
                    THEN BTRIM(p.qty)::numeric
                    ELSE 0
                END <=
                CASE
                    WHEN NULLIF(BTRIM(p.low_stock_threshold), '') ~ '^[0-9]+([.][0-9]+)?$'
                    THEN BTRIM(p.low_stock_threshold)::numeric
                    ELSE 5
                END
            ");
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';

            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'ILIKE', $search)
                  ->orWhere('p.product_code', 'ILIKE', $search);
            });
        }

        return $query
            ->orderBy('p.name')
            ->paginate(min(max((int) $request->input('per_page', 15), 1), 100));
    }

    public function orderStatusSummary(Request $request)
    {
        [$from, $to] = $this->dateRange($request);

        $query = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('master_types as mt', 'mt.id', '=', 'o.status_id')
            ->where('oi.seller_id', $this->sellerId());

        $this->applyDateRange($query, 'o.placed_at', $from, $to);

        return $query
            ->selectRaw("
                o.status_id,
                COALESCE(mt.value, 'Unknown') as status,
                COUNT(DISTINCT o.id) as total_orders,
                COALESCE(SUM(oi.total_amount), 0) as sales_amount
            ")
            ->groupBy('o.status_id', 'mt.value')
            ->orderByDesc('total_orders')
            ->get();
    }
}
