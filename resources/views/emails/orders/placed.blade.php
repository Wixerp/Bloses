<x-mail::message>
# Order Placed Successfully!

Hello {{ $order->customer_name ?? 'Customer' }},

Your order has been placed successfully.

**Order Number:** {{ $order->order_number }}

**Subtotal:** {{ number_format((float) $order->subtotal, 2) }}

**Discount:** {{ number_format((float) $order->discount_amount, 2) }}

**Total Amount:** {{ number_format((float) $order->total_amount, 2) }}

Thank you for shopping with us!

Regards,
{{ config('app.name') }}
</x-mail::message>
