<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductInventoryReportService
{
    private function stockExpression(): string
    {
        return "CASE
            WHEN BTRIM(COALESCE(p.stock_qty, '')) ~ '^[0-9]+(\\.[0-9]+)?$'
                THEN BTRIM(p.stock_qty)::numeric
            WHEN BTRIM(COALESCE(p.stock_quantity, '')) ~ '^[0-9]+(\\.[0-9]+)?$'
                THEN BTRIM(p.stock_quantity)::numeric
            WHEN BTRIM(COALESCE(p.qty, '')) ~ '^[0-9]+(\\.[0-9]+)?$'
                THEN BTRIM(p.qty)::numeric
            ELSE 0
        END";
    }

    private function priceExpression(string $column): string
    {
        return "CASE
            WHEN BTRIM(COALESCE(p.{$column}, '')) ~ '^[0-9]+(\\.[0-9]+)?$'
                THEN BTRIM(p.{$column})::numeric
            ELSE 0
        END";
    }

    private function baseQuery(Request $request)
    {
        $stock = $this->stockExpression();
        $price = $this->priceExpression('price');
        $cost = $this->priceExpression('purchase_price');
        $threshold = $this->priceExpression('low_stock_threshold');

        return DB::table('products as p')
            ->leftJoin(
                'seller_store_settings as store',
                'store.id',
                '=',
                'p.store_id'
            )
            ->leftJoin('users as u', 'u.id', '=', 'store.seller_id')
            ->leftJoin('master_types as st', 'st.id', '=', 'p.status_id')
            ->selectRaw("
                p.id,
                p.name,
                p.product_code,
                p.store_id,
                p.category_id,
                p.status_id,
                p.is_active,
                p.created_at,
                store.seller_id,
                store.store_name,
                u.name AS seller_name,
                COALESCE(st.value, st.description, 'Unknown') AS status,
                {$stock} AS stock_quantity,
                {$price} AS selling_price,
                {$cost} AS purchase_price,
                {$threshold} AS low_stock_threshold,
                ({$stock} * {$price}) AS selling_inventory_value,
                ({$stock} * {$cost}) AS purchase_inventory_value,
                CASE
                    WHEN {$stock} <= 0 THEN 'out_of_stock'
                    WHEN {$stock} <= {$threshold} THEN 'low_stock'
                    ELSE 'in_stock'
                END AS stock_status
            ")
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->where('p.created_at', '>=', $request->date_from . ' 00:00:00');
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $q->where(
                    'p.created_at',
                    '<',
                    date('Y-m-d', strtotime($request->date_to . ' +1 day')) . ' 00:00:00'
                );
            })
            ->when($request->filled('seller_id'), function ($q) use ($request) {
                $q->where('store.seller_id', $request->seller_id);
            })
            ->when($request->filled('category_id'), function ($q) use ($request) {
                $q->where('p.category_id', $request->category_id);
            })
            ->when($request->filled('status_id'), function ($q) use ($request) {
                $q->where('p.status_id', $request->status_id);
            })
            ->when($request->filled('is_active'), function ($q) use ($request) {
                $q->where('p.is_active', filter_var(
                    $request->is_active,
                    FILTER_VALIDATE_BOOLEAN
                ));
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%' . $request->search . '%';

                $q->where(function ($sub) use ($search) {
                    $sub->where('p.name', 'ilike', $search)
                        ->orWhere('p.product_code', 'ilike', $search);
                });
            });
    }

    public function summary(Request $request): array
    {
        $stock = $this->stockExpression();
        $price = $this->priceExpression('price');
        $cost = $this->priceExpression('purchase_price');
        $threshold = $this->priceExpression('low_stock_threshold');

        $query = DB::table('products as p')
            ->leftJoin(
                'seller_store_settings as store',
                'store.id',
                '=',
                'p.store_id'
            )
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->where('p.created_at', '>=', $request->date_from . ' 00:00:00');
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $q->where(
                    'p.created_at',
                    '<',
                    date('Y-m-d', strtotime($request->date_to . ' +1 day')) . ' 00:00:00'
                );
            })
            ->when($request->filled('seller_id'), function ($q) use ($request) {
                $q->where('store.seller_id', $request->seller_id);
            })
            ->when($request->filled('category_id'), function ($q) use ($request) {
                $q->where('p.category_id', $request->category_id);
            })
            ->when($request->filled('status_id'), function ($q) use ($request) {
                $q->where('p.status_id', $request->status_id);
            })
            ->when($request->filled('is_active'), function ($q) use ($request) {
                $q->where('p.is_active', filter_var(
                    $request->is_active,
                    FILTER_VALIDATE_BOOLEAN
                ));
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = '%' . $request->search . '%';

                $q->where(function ($sub) use ($search) {
                    $sub->where('p.name', 'ilike', $search)
                        ->orWhere('p.product_code', 'ilike', $search);
                });
            });

        $row = $query->selectRaw("
            COUNT(*) AS total_products,
            COUNT(*) FILTER (
                WHERE COALESCE(p.is_active, false) = true
            ) AS active_products,
            COUNT(*) FILTER (
                WHERE COALESCE(p.is_active, false) = false
            ) AS inactive_products,
            COUNT(*) FILTER (
                WHERE {$stock} <= 0
            ) AS out_of_stock_products,
            COUNT(*) FILTER (
                WHERE {$stock} > 0 AND {$stock} <= {$threshold}
            ) AS low_stock_products,
            COUNT(*) FILTER (
                WHERE {$stock} > {$threshold}
            ) AS in_stock_products,
            COALESCE(SUM({$stock}), 0) AS total_stock_quantity,
            COALESCE(SUM({$stock} * {$price}), 0) AS selling_inventory_value,
            COALESCE(SUM({$stock} * {$cost}), 0) AS purchase_inventory_value
        ")->first();

        return [
            'total_products' => (int) $row->total_products,
            'active_products' => (int) $row->active_products,
            'inactive_products' => (int) $row->inactive_products,
            'out_of_stock_products' => (int) $row->out_of_stock_products,
            'low_stock_products' => (int) $row->low_stock_products,
            'in_stock_products' => (int) $row->in_stock_products,
            'total_stock_quantity' => (float) $row->total_stock_quantity,
            'selling_inventory_value' => round((float) $row->selling_inventory_value, 2),
            'purchase_inventory_value' => round((float) $row->purchase_inventory_value, 2),
        ];
    }

    public function productList(Request $request)
    {
        return DB::query()
            ->fromSub($this->baseQuery($request), 'report')
            ->when($request->filled('stock_status'), function ($q) use ($request) {
                $q->where('stock_status', $request->stock_status);
            })
            ->orderByRaw("
                CASE stock_status
                    WHEN 'out_of_stock' THEN 1
                    WHEN 'low_stock' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('name')
            ->paginate((int) $request->input('per_page', 15));
    }

    public function report(Request $request): array
    {
        return [
            'filters' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'seller_id' => $request->seller_id,
                'category_id' => $request->category_id,
                'status_id' => $request->status_id,
                'is_active' => $request->is_active,
                'stock_status' => $request->stock_status,
                'search' => $request->search,
            ],
            'summary' => $this->summary($request),
            'products' => $this->productList($request),
        ];
    }
}
