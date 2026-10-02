<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class CommonHelper
{
    public static function generateOrderNumber(): string
    {
        $lastOrder = DB::table('orders')->orderByDesc('id')->value('order_number');

        if (!$lastOrder) {
            $number = 1;
        } else {
            preg_match('/(\d+)$/', $lastOrder, $matches);

            $number = isset($matches[1])
                ? ((int) $matches[1]) + 1
                : 1;
        }

        return 'BL-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public static function generateReturnNumber(): string
    {
        $lastReturn = DB::table('returns')
            ->orderByDesc('id')
            ->value('return_number');

        if (!$lastReturn) {
            $number = 1;
        } else {
            preg_match('/(\d+)$/', $lastReturn, $matches);

            $number = isset($matches[1])
                ? ((int) $matches[1]) + 1
                : 1;
        }

        return 'RET-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

}
