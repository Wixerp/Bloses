<?php

namespace App\Services\Seller;

use Illuminate\Support\Facades\{DB, Auth};

class SellerEarningService
{
    private const COMMISSION_PERCENTAGE = 10;

    private function getSellerId(): int
    {
        $sellerId = DB::table('sellers')->where('user_id', Auth::id())->value('id');

        if (!$sellerId) {
            abort(404, 'Seller profile not found.');
        }

        return (int) $sellerId;
    }

    private function getEarningStatusId(string $status): ?int
    {
        return DB::table('master_types')->where('key', 'earning_status')->where('value', $status)->value('id');
    }

    public function generateForDeliveredOrder(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {

            $deliveredStatusId = DB::table('master_types')->where('key', 'order_status')->where('value', 'delivered')->value('id');

            if (!$deliveredStatusId) {
                throw new \Exception(
                    'Delivered order status not found.'
                );
            }

            $order = DB::table('orders')->where('id', $orderId)->first();

            if (!$order) {
                throw new \Exception('Order not found.');
            }

            if ((int) $order->status_id !== (int) $deliveredStatusId) {
                return;
            }

            $sellerTotals = DB::table('order_items')->where('order_id', $orderId)
                ->select(
                    'seller_id',
                    DB::raw('SUM(total_amount) as gross_amount')
                )->groupBy('seller_id')->get();

            foreach ($sellerTotals as $sellerTotal) {

                $grossAmount = round((float) $sellerTotal->gross_amount, 2);

                if ($grossAmount <= 0) {
                    continue;
                }

                $commissionAmount = round(($grossAmount * self::COMMISSION_PERCENTAGE) / 100, 2);
                $netAmount = round($grossAmount - $commissionAmount, 2);

                $availableStatusId = DB::table('master_types')->where('key', 'earning_status')->where('value', 'available')->value('id');

                if (!$availableStatusId) {
                    throw new \Exception(
                        'Available earning status not found.'
                    );
                }

                $alreadyExists = DB::table('seller_earnings')->where('order_id', $orderId)->where('seller_id', $sellerTotal->seller_id)->exists();

                if ($alreadyExists) {
                    continue;
                }

                DB::table('seller_earnings')->insert([
                    'order_id' => $orderId,
                    'seller_id' => $sellerTotal->seller_id,
                    'gross_amount' => $grossAmount,
                    'commission_percentage' => self::COMMISSION_PERCENTAGE,
                    'commission_amount' => $commissionAmount,
                    'net_amount' => $netAmount,
                    'status_id' => $availableStatusId,
                    'available_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function summary(): array
    {
        $sellerId = $this->getSellerId();
        $availableStatusId = $this->getEarningStatusId('available');
        $paidStatusId = $this->getEarningStatusId('paid');

        $totals = DB::table('seller_earnings')->where('seller_id', $sellerId)
            ->selectRaw('COALESCE(SUM(gross_amount), 0) as total_sales')
            ->selectRaw('COALESCE(SUM(commission_amount), 0) as total_commission')
            ->selectRaw('COALESCE(SUM(net_amount), 0) as total_earnings')
            ->selectRaw('COALESCE(SUM(CASE WHEN status_id = ? THEN net_amount ELSE 0 END), 0) as available_earnings',[$availableStatusId])
            ->selectRaw('COALESCE(SUM(CASE WHEN status_id = ? THEN net_amount ELSE 0 END), 0) as paid_earnings',[$paidStatusId])
            ->first();

        return [
            'total_sales' => (float) $totals->total_sales,
            'total_commission' => (float) $totals->total_commission,
            'total_earnings' => (float) $totals->total_earnings,
            'available_earnings' => (float) $totals->available_earnings,
            'paid_earnings' => (float) $totals->paid_earnings,
        ];
    }

    // public function listing(array $filters = [])
    // {
    //     $sellerId = $this->getSellerId();
    //     $query = DB::table('seller_earnings')
    //         ->join('orders', 'orders.id', '=', 'seller_earnings.order_id')
    //         ->leftJoin('master_types as earning_status', 'earning_status.id', '=', 'seller_earnings.status_id')
    //         ->leftJoin('payments', 'payments.order_id', '=', 'seller_earnings.order_id')
    //         ->where('seller_earnings.seller_id', $sellerId)
    //         ->select([
    //             'seller_earnings.id','seller_earnings.order_id',
    //             'orders.order_number',
    //             'seller_earnings.gross_amount','seller_earnings.commission_percentage','seller_earnings.commission_amount',
    //             'seller_earnings.net_amount','seller_earnings.status_id',
    //             'earning_status.value as status','earning_status.description as status_name',
    //             'payments.amount as payment_amount','payments.currency as payment_currency','payments.paid_at',
    //             'seller_earnings.available_at','seller_earnings.created_at',
    //         ]);

    //     if (!empty($filters['status'])) {
    //         $statusId = $this->getEarningStatusId($filters['status']);

    //         if ($statusId) {
    //             $query->where('seller_earnings.status_id', $statusId);
    //         }
    //     }

    //     return $query->orderByDesc('seller_earnings.id')->get();
    // }
    public function listing(array $filters = [])
    {
        $sellerId = $this->getSellerId();

        $query = DB::table('seller_earnings')
            ->join('orders', 'orders.id', '=', 'seller_earnings.order_id')
            ->leftJoin(
                'master_types as earning_status',
                'earning_status.id',
                '=',
                'seller_earnings.status_id'
            )
            ->where('seller_earnings.seller_id', $sellerId)
            ->select([
                'seller_earnings.id',
                'seller_earnings.order_id',
                'orders.order_number',
                'seller_earnings.gross_amount',
                'seller_earnings.commission_percentage',
                'seller_earnings.commission_amount',
                'seller_earnings.net_amount',
                'seller_earnings.status_id',
                'earning_status.value as status',
                'earning_status.description as status_name',
                'seller_earnings.available_at',
                'seller_earnings.created_at',
            ]);

        if (!empty($filters['status'])) {
            $statusId = $this->getEarningStatusId($filters['status']);

            if (!$statusId) {
                return $query->whereRaw('1 = 0')->get();
            }

            $query->where('seller_earnings.status_id', $statusId);
        }

        return $query->orderByDesc('seller_earnings.id')->get();
    }
}
