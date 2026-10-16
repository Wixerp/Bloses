<?php

namespace App\Services\Order;
use Illuminate\Support\Facades\DB;
use App\Helpers\CommonHelper;
use App\Services\Notification\NotificationService;

class OrderService{

    public function __construct(
        private NotificationService $notificationService
    ) {}

    // public function createOrder(array $data)
    // {
    //     if (!auth()->check()) {
    //         abort(401, 'Please login to place an order.');
    //     }

    //     $userId = auth()->id();
    //     return DB::transaction(function () use ($data, $userId) {
    //         $subtotal = (float) $data['subtotal'];
    //         $discountAmount = (float) ($data['discount_amount'] ?? 0);
    //         $taxAmount = (float) ($data['tax_amount'] ?? $data['tax'] ?? 0);
    //         $shippingAmount = (float) ($data['shipping_amount'] ?? $data['shipping_cost'] ?? 0);
    //         $totalAmount = (float) ($data['total'] ?? $data['total_amount'] ?? 0);
    //         $processingStatusId = DB::table('master_types')->where('key', 'order_status')->where('value', 'processing')->value('id');

    //         if (!$processingStatusId) {
    //             abort(500, 'Processing order status not found.');
    //         }
    //         $orderId = DB::table('orders')->insertGetId([
    //             'order_number' => CommonHelper::generateOrderNumber(),
    //             'user_id' => $userId,
    //             'status_id' => $processingStatusId,
    //             'payment_method_id' => $data['payment_method_id'] ?? null,
    //             'subtotal' => $subtotal,
    //             'discount_amount' => $discountAmount,
    //             'tax_amount' => $taxAmount,
    //             'shipping_amount' => $shippingAmount,
    //             'total_amount' => $totalAmount,
    //             'customer_note' => $data['customer_note'] ?? null,
    //             'plased_at' => now(),
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ]);

    //         DB::table('order_status_history')->insert([
    //             'order_id' => $orderId,
    //             'status_id' => $processingStatusId,
    //             'previous_status_id' => null,
    //             'changed_by' => $userId,
    //             'comment' => 'Order placed',
    //             'changed_at' => now(),
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ]);

    //         foreach ($data['items'] as $item) {
    //             $product = DB::table('products')->where('id', $item['product_id'])->first();

    //             if (!$product) {
    //                 abort(404, 'Product not found.');
    //             }

    //             $variant = null;

    //             if (!empty($item['product_variant_id'])) {
    //                 $variant = DB::table('product_variants')->where('id', $item['product_variant_id'])->where('product_id', $item['product_id'])->first();

    //                 if (!$variant) {
    //                     abort(404, 'Product variant not found.');
    //                 }
    //             }

    //             $store = DB::table('seller_store_settings')->where('id', $product->store_id)->first();

    //             if (!$store) {
    //                 abort(404, 'Seller store not found.');
    //             }

    //             $quantity = (int) $item['quantity'];
    //             $unitPrice = $variant ? ($variant->price ?? $product->price) : $product->price;
    //             $unitPrice = (float) $unitPrice;
    //             $totalItemAmount = $unitPrice * $quantity;

    //             DB::table('order_items')->insert([
    //                 'order_id' => $orderId,
    //                 'product_id' => $product->id,
    //                 'product_variant_id' => $variant?->id,
    //                 'seller_id' => $store->seller_id,
    //                 'product_name' => $product->name,
    //                 'variant_name' => $variant?->name,
    //                 'sku' => $variant?->sku ?? $product->product_code,
    //                 'unit_price' => $unitPrice,
    //                 'quantity' => $quantity,
    //                 'discount_amount' => 0,
    //                 'tax_amount' => 0,
    //                 'shipping_amount' => 0,
    //                 'total_amount' => $totalItemAmount,
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ]);
    //         }

    //         $address = $data['address'];

    //         DB::table('order_addresses')->insert([
    //             'order_id' => $orderId,
    //             'type_id' => null,
    //             'first_name' => $address['first_name'],
    //             'last_name' => $address['last_name'] ?? null,
    //             'phone' => $address['phone'],
    //             'email' => $address['email'] ?? null,
    //             'address_line_1' => $address['address_line_1'],
    //             'address_line_2' => $address['address_line_2'] ?? null,
    //             'city' => $address['city'],
    //             'state' => $address['state'] ?? null,
    //             'postal_code' => $address['postal_code'] ?? null,
    //             'country' => $address['country'],
    //             'delivery_instructions' => $address['delivery_instructions'] ?? null,
    //             'created_at' => now(),
    //             'updated_at' => now(),
    //         ]);

    //         $order = DB::table('orders')->where('id', $orderId)->first();

    //         $this->notificationService->create([
    //             'user_id' => $store->seller_id,
    //             'type' => 'order',
    //             'order_id' => $orderId,
    //             'title' => 'Order Placed',
    //             'message' => "Your order {$order->order_number} has been placed successfully.",
    //             'action_url' => "/buyer/orders/{$orderId}",
    //         ]);

    //         return $order;
    //     });
    // }
    public function createOrder(array $data)
    {
        if (!auth()->check()) {
            abort(401, 'Please login to place an order.');
        }

        $userId = (int) auth()->id();

        return \Illuminate\Support\Facades\DB::transaction(function () use ($data, $userId) {
            $now = now();
            $db = \Illuminate\Support\Facades\DB::class;

            $preparedItems = [];
            $subtotal = 0;
            $sellerUserIds = [];

            foreach ($data['items'] as $item) {
                $product = $db::table('products')
                    ->where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    abort(404, 'Product not found.');
                }

                $variant = null;

                if (!empty($item['product_variant_id'])) {
                    $variant = $db::table('product_variants')
                        ->where('id', $item['product_variant_id'])
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$variant) {
                        abort(404, 'Product variant not found.');
                    }
                }

                $store = $db::table('seller_store_settings')
                    ->where('id', $product->store_id)
                    ->first();

                if (!$store) {
                    abort(404, 'Seller store not found.');
                }

                $quantity = (int) $item['quantity'];

                $unitPrice = (float) (
                    $variant
                        ? ($variant->price ?? $product->price)
                        : $product->price
                );

                if ($unitPrice < 0) {
                    abort(422, 'Invalid product price.');
                }

                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineTotal;
                $sellerUserId = $db::table('sellers')
                    ->where('id', $store->seller_id)
                    ->value('user_id');

                if ($sellerUserId) {
                    $sellerUserIds[(int) $sellerUserId] = (int) $sellerUserId;
                }

                $preparedItems[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'store' => $store,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $taxAmount = (float) (
                $data['tax_amount'] ?? $data['tax'] ?? 0
            );

            $shippingAmount = (float) (
                $data['shipping_amount']
                    ?? $data['shipping_cost']
                    ?? 0
            );

            $coupon = null;
            $discountAmount = 0;

            if (!empty($data['coupon_code'])) {
                $couponCode = trim($data['coupon_code']);

                $coupon = $db::table('coupons')
                    ->whereRaw('LOWER(code) = ?', [strtolower($couponCode)])
                    ->lockForUpdate()
                    ->first();

                if (!$coupon || !$coupon->is_active) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'coupon_code' => ['Invalid or inactive coupon code.'],
                    ]);
                }

                if (
                    $coupon->starts_at &&
                    $now->lt(\Illuminate\Support\Carbon::parse($coupon->starts_at))
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'coupon_code' => ['This coupon is not active yet.'],
                    ]);
                }

                if (
                    $coupon->expires_at &&
                    $now->gt(\Illuminate\Support\Carbon::parse($coupon->expires_at))
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'coupon_code' => ['This coupon has expired.'],
                    ]);
                }

                if ($subtotal < (float) $coupon->minimum_order_amount) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'coupon_code' => [
                            'Minimum order amount is '
                            . number_format(
                                (float) $coupon->minimum_order_amount,
                                2
                            ),
                        ],
                    ]);
                }

                if (
                    $coupon->usage_limit !== null &&
                    (int) $coupon->used_count >= (int) $coupon->usage_limit
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'coupon_code' => ['This coupon has reached its usage limit.'],
                    ]);
                }

                if ($coupon->user_usage_limit !== null) {
                    $userUsage = $db::table('coupon_usages')
                        ->where('coupon_id', $coupon->id)
                        ->where('user_id', $userId)
                        ->count();

                    if ($userUsage >= (int) $coupon->user_usage_limit) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'coupon_code' => [
                                'You have reached your usage limit for this coupon.',
                            ],
                        ]);
                    }
                }

                $discountType = $db::table('master_types')
                    ->where('id', $coupon->discount_type_id)
                    ->first(['key', 'value', 'description']);

                $type = strtolower(trim((string) (
                    $discountType->value
                        ?? $discountType->key
                        ?? $discountType->description
                        ?? ''
                )));

                if ($type === 'percentage') {
                    $discountAmount =
                        $subtotal * (float) $coupon->discount_value / 100;
                } elseif (in_array($type, [
                    'fixed',
                    'fixed_amount',
                    'amount',
                ], true)) {
                    $discountAmount = (float) $coupon->discount_value;
                } else {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'coupon_code' => [
                            'Unsupported coupon discount type.',
                        ],
                    ]);
                }

                if ($coupon->maximum_discount !== null) {
                    $discountAmount = min(
                        $discountAmount,
                        (float) $coupon->maximum_discount
                    );
                }

                $discountAmount = round(
                    min(max($discountAmount, 0), $subtotal),
                    2
                );
            }

            $totalAmount = round(
                max(0, $subtotal - $discountAmount)
                    + $taxAmount
                    + $shippingAmount,
                2
            );

            $processingStatusId = $db::table('master_types')
                ->where('key', 'order_status')
                ->where('value', 'processing')
                ->value('id');

            if (!$processingStatusId) {
                abort(500, 'Processing order status not found.');
            }

            $orderId = $db::table('orders')->insertGetId([
                'order_number' => \App\Helpers\CommonHelper::generateOrderNumber(),
                'user_id' => $userId,
                'status_id' => $processingStatusId,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingAmount,
                'total_amount' => $totalAmount,
                'customer_note' => $data['customer_note'] ?? null,
                'plased_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $db::table('order_status_history')->insert([
                'order_id' => $orderId,
                'status_id' => $processingStatusId,
                'previous_status_id' => null,
                'changed_by' => $userId,
                'comment' => 'Order placed',
                'changed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($preparedItems as $prepared) {
                $product = $prepared['product'];
                $variant = $prepared['variant'];
                $store = $prepared['store'];

                $db::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'seller_id' => $store->seller_id,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->name,
                    'sku' => $variant?->sku ?? $product->product_code,
                    'unit_price' => $prepared['unit_price'],
                    'quantity' => $prepared['quantity'],
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'shipping_amount' => 0,
                    'total_amount' => $prepared['line_total'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $address = $data['address'];

            $db::table('order_addresses')->insert([
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
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($coupon) {
                $db::table('coupon_usages')->insert([
                    'coupon_id' => $coupon->id,
                    'user_id' => $userId,
                    'order_id' => $orderId,
                    'discount_amount' => $discountAmount,
                    'used_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $db::table('coupons')
                    ->where('id', $coupon->id)
                    ->increment('used_count', 1, [
                        'updated_at' => $now,
                    ]);
            }

            foreach (array_values($sellerUserIds) as $sellerUserId) {
                $this->notificationService->create([
                    'user_id' => $sellerUserId,
                    'type' => 'order',
                    'order_id' => $orderId,
                    'title' => 'Order Placed',
                    'message' => "Order placed successfully. Order ID: {$orderId}.",
                    'action_url' => "/seller/orders/{$orderId}",
                ]);
            }

            $order = $db::table('orders')
                ->where('id', $orderId)
                ->first();

            // return [
            //     'order' => $order,
            //     'coupon' => $coupon ? [
            //         'id' => (int) $coupon->id,
            //         'code' => $coupon->code,
            //         'discount_amount' => $discountAmount,
            //     ] : null,
            // ];

            // $order = DB::table('orders')
            //     ->where('id', $orderId)
            //     ->first();

            return $order;
        });
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
                ->select(['id','product_id','product_variant_id','product_name','variant_name',
                    'sku','unit_price as price','quantity as qty','discount_amount',
                    'tax_amount','shipping_amount','total_amount',
                ])->get();

            $returns = DB::table('returns')
                ->leftJoin('master_types as return_status','return_status.id','=','returns.status_id')
                ->where('returns.order_id', $order->id)->where('returns.user_id', $userId)
                ->select(['returns.id','returns.return_number','returns.seller_id','returns.status_id',
                    'return_status.value as status','return_status.description as status_name',
                    'returns.reason_id','returns.customer_note','returns.seller_note','returns.admin_note',
                    'returns.requested_at','returns.approved_at','returns.picked_up_at',
                    'returns.rejected_at','returns.completed_at','returns.created_at',
                ])->orderByDesc('returns.id')->get();

            $order->is_return_requested = $returns->isNotEmpty();
            $order->returns = $returns;

            foreach ($order->returns as $return) {
                $return->items = DB::table('return_items')
                    ->where('return_id', $return->id)
                    ->select([
                        'id','order_item_id','quantity','unit_price','total_amount',
                        'reason_id','customer_note','admin_note',
                    ])->get();
            }

            $history = DB::table('order_status_history')
                ->join('master_types as status','status.id','=','order_status_history.status_id'
                )->where('order_status_history.order_id', $order->id)
                ->select([
                    'order_status_history.comment','order_status_history.status_id',
                    'status.value','status.description','order_status_history.changed_at',
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

    public function createReturn(array $data)
    {
        if (!auth()->check()) {
            abort(401, 'Please login to request a return.');
        }

        $userId = auth()->id();

        return DB::transaction(function () use ($data, $userId) {

            // Order check
            $order = DB::table('orders')->where('id', $data['order_id'])->where('user_id', $userId)->first();

            if (!$order) {
                abort(404, 'Order not found.');
            }

            // Order items
            $orderItems = DB::table('order_items')->where('order_id', $order->id)->get();

            if ($orderItems->isEmpty()) {
                abort(400, 'No items found in this order.');
            }

            $existingReturn = DB::table('returns')->where('order_id', $order->id)->where('user_id', $userId)
                ->whereNull('rejected_at')->whereNull('completed_at')->exists();

            if ($existingReturn) {
                abort(400, 'A return request already exists for this order.');
            }

            $requestedStatusId = DB::table('master_types')->where('key', 'return_status')->where('value', 'return_requested')->value('id');

            if (!$requestedStatusId) {
                abort(400, 'Return requested status not found.');
            }

            $customerNote = trim(
                $data['reason'] . ' - ' . $data['issue']
            );

            $groupedItems = $orderItems->groupBy('seller_id');
            $createdReturns = [];

            foreach ($groupedItems as $sellerId => $items) {
                $returnNumber = CommonHelper::generateReturnNumber();

                $returnId = DB::table('returns')->insertGetId([
                    'return_number' => $returnNumber,
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'seller_id' => $sellerId,
                    'status_id' => $requestedStatusId,
                    'customer_note' => $customerNote,
                    'requested_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($items as $item) {

                    $totalAmount = (float) $item->unit_price * (int) $item->quantity;

                    DB::table('return_items')->insert([
                        'return_id' => $returnId,
                        'order_item_id' => $item->id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_amount' => $totalAmount,
                        'reason_id' => null,
                        'customer_note' => $customerNote,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $createdReturns[] = DB::table('returns')->where('id', $returnId)->first();
            }
            $returnIds = collect($createdReturns)->pluck('id')->implode(',');

            $this->notificationService->create([
                'user_id' => $userId,
                'type' => 'return',
                'order_id' => $order->id,
                'return_id' => $createdReturns[0]->id,
                'title' => 'Return Request Created',
                'message' => "Your return request for order {$order->order_number} has been submitted successfully.",
                'action_url' => "/buyer/orders/{$order->id}",
            ]);

            return $createdReturns;
        });
    }

    public function sellerOrders()
    {
        if (!auth()->check()) {
            abort(401, 'Please login.');
        }

        $sellerId = DB::table('sellers')->where('user_id', auth()->id())->value('id');

        if (!$sellerId) {
            abort(404, 'Seller profile not found.');
        }

        $orders = DB::table('orders')->join('order_items','order_items.order_id','=','orders.id')
            ->join('users','users.id','=','orders.user_id')
            ->leftJoin('master_types as status','status.id','=','orders.status_id')
            ->where('order_items.seller_id', $sellerId)
            ->select(['orders.id','orders.order_number','orders.status_id','status.description AS status_name','users.name as buyer_name','users.email as buyer_email',
                DB::raw("TO_CHAR(orders.created_at, 'YYYY-MM-DD') as order_date"),
                DB::raw('SUM(order_items.total_amount) as total_price'),
            ])
            ->groupBy('orders.id','orders.order_number','users.name','orders.status_id','status.description','users.email','orders.created_at')
            ->orderByDesc('orders.id')->get();

        return $orders;
    }

    public function sellerOrderDetail(int $orderId)
    {
        if (!auth()->check()) {
            abort(401, 'Please login.');
        }

        $sellerId = DB::table('sellers')->where('user_id', auth()->id())->value('id');

        if (!$sellerId) {
            abort(404, 'Seller profile not found.');
        }

        $orderExists = DB::table('order_items')->where('order_id', $orderId)->where('seller_id', $sellerId)->exists();

        if (!$orderExists) {
            abort(404, 'Order not found.');
        }

        $order = DB::table('orders')
            ->leftJoin('master_types as status','status.id','=','orders.status_id')
            ->leftJoin('master_types as payment','payment.id','=','orders.payment_method_id')
            ->where('orders.id', $orderId)
            ->select(['orders.id','orders.order_number','orders.user_id','orders.status_id',
                'status.value as status','status.description as status_name',
                'orders.payment_method_id','payment.value as payment_method','payment.description as payment_method_name',
                'orders.subtotal','orders.discount_amount','orders.tax_amount','orders.shipping_amount','orders.total_amount',
                'orders.customer_note',
                'orders.plased_at','orders.confirmed_at','orders.cancelled_at','orders.completed_at',
                'orders.created_at','orders.updated_at',
            ])->first();

        if (!$order) {
            abort(404, 'Order not found.');
        }

        $order->buyer = DB::table('users')
            ->where('id', $order->user_id)
            ->select([
                'id','name','email','phone',
            ])->first();

        $order->address = DB::table('order_addresses')->where('order_id', $orderId)->first();

        $order->items = DB::table('order_items')
            ->where('order_id', $orderId)->where('seller_id', $sellerId)
            ->select([
                'id','product_id','product_variant_id','product_name','variant_name',
                'sku','unit_price','quantity','discount_amount','tax_amount',
                'shipping_amount','total_amount','created_at',
                DB::raw("(SELECT CONCAT('" . config('app.url') . "/storage/', pi.image_path)
                        FROM product_images pi
                        WHERE pi.product_id = order_items.product_id
                        ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC
                        LIMIT 1
                    ) AS product_image
                "),
            ])->get();

        $order->seller_total = $order->items->sum(function ($item) {
            return (float) $item->total_amount;
        });

        $order->seller_items_count = $order->items->sum('quantity');

        $order->shipments = DB::table('shipments')
            ->where('order_id', $orderId)->where('seller_id', $sellerId)
            ->select([
                'id','status_id','carrier','tracking_number','tracking_url','shipping_amount',
                'shipped_at','estimated_delivery_at','delivered_at','returned_at','notes',
            ])->orderByDesc('id')->get();

        foreach ($order->shipments as $shipment) {

            $shipment->items = DB::table('shipment_items')
                ->join('order_items','order_items.id','=','shipment_items.order_item_id')
                ->where('shipment_items.shipment_id', $shipment->id)->where('order_items.seller_id', $sellerId)
                ->select([
                    'shipment_items.id','shipment_items.order_item_id','shipment_items.quantity',
                ])->get();
        }

        $order->status_history = DB::table('order_status_history')
            ->leftJoin('master_types as status','status.id','=','order_status_history.status_id')
            ->where('order_status_history.order_id', $orderId)
            ->select([
                'order_status_history.id','order_status_history.status_id','status.value as status',
                'status.description as status_name','order_status_history.comment','order_status_history.changed_at',
            ])->orderBy('order_status_history.id')->get();

        return response()->json([
            'success' => true,
            'message' => 'Seller order detail fetched successfully.',
            'data' => $order,
        ]);
    }

    public function updateOrderStatus(int $orderId, int $statusId)
    {
        if (!auth()->check()) {
            abort(401, 'Please login.');
        }

        $sellerId = DB::table('sellers')->where('user_id', auth()->id())->value('id');

        if (!$sellerId) {
            abort(404, 'Seller profile not found.');
        }

        $order = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.id', $orderId)->where('order_items.seller_id', $sellerId)
            ->select([
                'orders.id',
                'orders.status_id',
                'orders.user_id',
                'orders.order_number',
            ])
            ->first();

        if (!$order) {
            abort(404, 'Order not found.');
        }

        $status = DB::table('master_types')->where('id', $statusId)->where('key', 'order_status')->first();

        if (!$status) {
            abort(400, 'Invalid order status.');
        }

        $previousStatusId = $order->status_id;

        DB::table('orders')->where('id', $orderId)
            ->update([
                'status_id' => $statusId,
                'updated_at' => now(),
            ]);

        // Status history
        DB::table('order_status_history')->insert([
            'order_id' => $orderId,
            'status_id' => $statusId,
            'previous_status_id' => $previousStatusId,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($status->value === 'delivered') {
            $shipmentDeliveredStatusId = DB::table('master_types')->where('key', 'order_status')->where('value', 'delivered')->value('id');

            if (!$shipmentDeliveredStatusId) {
                abort(400, 'Shipment delivered status not found.');
            }

            DB::table('shipments')->where('order_id', $orderId)->where('seller_id', $sellerId)
                ->update([
                    'status_id' => $shipmentDeliveredStatusId,
                    'delivered_at' => now(),
                    'updated_at' => now(),
                ]);
                $userId = auth()->id();
                $this->notificationService->create([
                    'user_id' => $userId,
                    'type' => 'order',
                    'order_id' => $orderId,
                    'title' => 'Order Delivered',
                    'message' => "Your order {$order->order_number} has been delivered successfully.",
                    'action_url' => "/buyer/orders/{$orderId}",
                ]);
        }

        return DB::table('orders')
            ->leftJoin('master_types as status', 'status.id', '=', 'orders.status_id')
            ->where('orders.id', $orderId)
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.status_id',
                'status.value as status',
                'status.description as status_name',
            ])
            ->first();
    }

    public function sellerReturnOrders()
    {
        if (!auth()->check()) {
            abort(401, 'Please login.');
        }

        $sellerId = DB::table('sellers')
            ->where('user_id', auth()->id())
            ->value('id');

        if (!$sellerId) {
            abort(404, 'Seller profile not found.');
        }

        $returns = DB::table('returns')
            ->leftJoin('orders', 'orders.id', '=', 'returns.order_id')
            ->leftJoin('users', 'users.id', '=', 'returns.user_id')
            ->leftJoin(
                'master_types as status',
                'status.id',
                '=',
                'returns.status_id'
            )
            ->where('returns.seller_id', $sellerId)
            ->select([
                'returns.id',
                'returns.return_number',
                'returns.order_id',
                'orders.order_number',
                'returns.user_id',
                'users.name as buyer_name',
                'users.email as buyer_email',
                'returns.status_id',
                'status.description as status_name',
                'returns.customer_note',
                'returns.seller_note',
                'returns.carrier',
                'returns.tracking_number',
                'returns.requested_at',
                'returns.approved_at',
                'returns.picked_up_at',
                // 'returns.recevied_at',
                'returns.rejected_at',
                'returns.completed_at',

                'returns.created_at',
            ])
            ->orderByDesc('returns.id')
            ->get();

        foreach ($returns as $return) {

            $return->items = DB::table('return_items')
                ->join(
                    'order_items',
                    'order_items.id',
                    '=',
                    'return_items.order_item_id'
                )
                ->where('return_items.return_id', $return->id)
                ->where('order_items.seller_id', $sellerId)
                ->select([
                    'return_items.id',
                    'return_items.order_item_id',

                    'order_items.product_id',
                    'order_items.product_variant_id',

                    'order_items.product_name',
                    'order_items.variant_name',
                    'order_items.sku',

                    'return_items.quantity',
                    'return_items.unit_price',
                    'return_items.total_amount',

                    'return_items.reason_id',
                    'return_items.customer_note',
                ])
                ->get();

            $return->total_amount = $return->items->sum(
                fn ($item) => (float) $item->total_amount
            );

            $return->items_count = $return->items->sum('quantity');
        }

        return $returns;
    }

}
