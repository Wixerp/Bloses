<?php

namespace App\Services\Order;
use Illuminate\Support\Facades\DB;

class OrderService{

    public function createOrder(array $data)
    {
        if (!auth()->check()) {
            abort(401, 'Please login to place an order.');
        }

        $userId = auth()->id();
        return DB::transaction(function () use ($data, $userId) {
            $subtotal = (float) $data['subtotal'];
            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $taxAmount = (float) ($data['tax_amount'] ?? $data['tax'] ?? 0);
            $shippingAmount = (float) ($data['shipping_amount'] ?? $data['shipping_cost'] ?? 0);
            $totalAmount = (float) ($data['total'] ?? $data['total_amount'] ?? 0);
            $processingStatusId = DB::table('master_types')->where('key', 'order_status')->where('value', 'processing')->value('id');

            if (!$processingStatusId) {
                abort(500, 'Processing order status not found.');
            }
            $orderId = DB::table('orders')->insertGetId([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $userId,
                'status_id' => $processingStatusId,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingAmount,
                'total_amount' => $totalAmount,
                'customer_note' => $data['customer_note'] ?? null,
                'plased_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('order_status_history')->insert([
                'order_id' => $orderId,
                'status_id' => $processingStatusId,
                'previous_status_id' => null,
                'changed_by' => $userId,
                'comment' => 'Order placed',
                'changed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($data['items'] as $item) {
                $product = DB::table('products')->where('id', $item['product_id'])->first();

                if (!$product) {
                    abort(404, 'Product not found.');
                }

                $variant = null;

                if (!empty($item['product_variant_id'])) {
                    $variant = DB::table('product_variants')->where('id', $item['product_variant_id'])->where('product_id', $item['product_id'])->first();

                    if (!$variant) {
                        abort(404, 'Product variant not found.');
                    }
                }

                $store = DB::table('seller_store_settings')->where('id', $product->store_id)->first();

                if (!$store) {
                    abort(404, 'Seller store not found.');
                }

                $quantity = (int) $item['quantity'];
                $unitPrice = $variant ? ($variant->price ?? $product->price) : $product->price;
                $unitPrice = (float) $unitPrice;
                $totalItemAmount = $unitPrice * $quantity;

                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'seller_id' => $store->seller_id,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->name,
                    'sku' => $variant?->sku ?? $product->product_code,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'shipping_amount' => 0,
                    'total_amount' => $totalItemAmount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $address = $data['address'];

            DB::table('order_addresses')->insert([
                'order_id' => $orderId,
                'type_id' => null,
                'first_name' => $address['first_name'],
                'last_name' => $address['last_name'] ?? null,
                'phone' => $address['phone'],
                'email' => $address['email'] ?? null,
                'address_line_1' => $address['address_line_1'],
                'address_line_2' => $address['address_line_2'] ?? null,
                'city' => $address['city'],
                'state' => $address['state'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'country' => $address['country'],
                'delivery_instructions' => $address['delivery_instructions'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('orders')->where('id', $orderId)->first();
        });
    }

    private function generateOrderNumber(): string
    {
        $lastOrder = DB::table('orders')->orderByDesc('id')->value('order_number');

        if (!$lastOrder) {
            $number = 1;
        } else {
            preg_match('/(\d+)$/', $lastOrder, $matches);
            $number = isset($matches[1]) ? ((int) $matches[1]) + 1 : 1;
        }

        return 'BL-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function getMyOrders()
    {
        if (!auth()->check()) {
            abort(401, 'Please login to view your orders.');
        }

        $userId = auth()->id();

        $orders = DB::table('orders')
            ->leftJoin('master_types as payment_method','payment_method.id','=','orders.payment_method_id')
            ->leftJoin('master_types as order_status','order_status.id','=','orders.status_id')
            ->where('orders.user_id', $userId)
            ->select(['orders.id','orders.order_number','orders.payment_method_id',
                'payment_method.description as payment_method',
                'orders.status_id','order_status.value as status','order_status.description as status_name',
                'orders.subtotal','orders.discount_amount','orders.tax_amount','orders.shipping_amount',
                'orders.total_amount','orders.created_at',
            ])->orderByDesc('orders.id')->get();

        foreach ($orders as $order) {
            $order->items = DB::table('order_items')
                ->where('order_id', $order->id)
                ->select(['id','product_id','product_variant_id','product_name',
                    'variant_name','sku','unit_price as price','quantity as qty',
                    'discount_amount','tax_amount','shipping_amount','total_amount',
                ])->get();

            $history = DB::table('order_status_history')
                ->join('master_types as status','status.id','=','order_status_history.status_id')
                ->where('order_status_history.order_id', $order->id)
                ->select(['order_status_history.comment','order_status_history.status_id','status.value','status.description','order_status_history.changed_at',
                ])->orderBy('order_status_history.id')->get();

            $order->order_track = [
                'processing' => false,
                'shipping' => false,
                'delivered' => false,
            ];

            foreach ($history as $track) {
                if ($track->value === 'processing') {
                    $order->order_track['processing'] = true;
                }
                if ($track->value === 'shipping') {
                    $order->order_track['shipping'] = true;
                }
                if ($track->value === 'delivered') {
                    $order->order_track['delivered'] = true;
                }
            }

            $order->status_history = $history;
        }

        return $orders;
    }

    public function cancelOrder(int $orderId, string $reason)
    {
        if (!auth()->check()) {
            abort(401, 'Please login to cancel your order.');
        }

        $userId = auth()->id();

        return DB::transaction(function () use ($orderId, $userId, $reason) {

            $order = DB::table('orders')->where('id', $orderId)->where('user_id', $userId)->first();

            if (!$order) {
                abort(404, 'Order not found.');
            }

            $currentStatus = DB::table('master_types')->where('id', $order->status_id)->where('key', 'order_status')->first();

            if (!$currentStatus) {
                abort(400, 'Order status not found.');
            }

            if ($currentStatus->value !== 'processing') {
                abort(400, 'Only processing orders can be cancelled.');
            }

            $cancelledStatusId = DB::table('master_types')->where('key', 'order_status')->where('value', 'cancelled')->value('id');

            if (!$cancelledStatusId) {
                abort(500, 'Cancelled order status not found.');
            }

            DB::table('orders')->where('id', $orderId)
                ->update([
                    'status_id' => $cancelledStatusId,
                    'cancelled_at' => now(),
                    'updated_at' => now(),
                ]);

            return DB::table('orders')
                ->where('id', $orderId)
                ->first();
        });
    }

}
