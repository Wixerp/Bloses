<?php

namespace App\Services\Admin\Coupon;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{

    public function create(array $data): object
    {
        $code = strtoupper(trim($data['code']));

        $exists = DB::table('coupons')->whereRaw('LOWER(code) = ?', [strtolower($code)])->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'This coupon code already exists.',
            ]);
        }

        $discountType = DB::table('master_types')->where('id', $data['discount_type_id'])->first();

        if (!$discountType) {
            throw ValidationException::withMessages([
                'discount_type_id' => 'Invalid discount type.',
            ]);
        }

        if (strtolower($discountType->value) === 'percentage' && $data['discount_value'] > 100)
        {
            throw ValidationException::withMessages([
                'discount_value' => 'Percentage discount cannot be greater than 100.',
            ]);
        }

        $couponId = DB::table('coupons')->insertGetId([
            'code' => $code,
            'name' => $data['name'] ?? $code,
            'description' => $data['description'] ?? null,
            'discount_type_id' => $data['discount_type_id'],
            'discount_value' => $data['discount_value'],
            'maximum_discount' => $data['maximum_discount'] ?? null,
            'minimum_order_amount' => $data['minimum_order_amount'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'user_usage_limit' => $data['user_usage_limit'] ?? null,
            'used_count' => 0,
            'starts_at' => $data['starts_at'] ?? now(),
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->getCoupon($couponId);
    }

    public function getCoupons(array $filters = [])
    {
        $query = DB::table('coupons')
            ->leftJoin('master_types as discount_type','discount_type.id','=','coupons.discount_type_id')
            ->select(
                'coupons.id','coupons.code','coupons.name','coupons.description','coupons.discount_type_id',
                'discount_type.value as discount_type','discount_type.description as discount_type_label',
                'coupons.discount_value','coupons.maximum_discount','coupons.minimum_order_amount','coupons.usage_limit',
                'coupons.user_usage_limit','coupons.used_count','coupons.starts_at','coupons.expires_at',
                'coupons.is_active','coupons.created_at','coupons.updated_at'
            );

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('coupons.code', 'ILIKE', "%{$search}%")
                    ->orWhere('coupons.name', 'ILIKE', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('coupons.is_active',
                filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)
            );
        }

        return $query->orderByDesc('coupons.created_at')->get()
            ->map(function ($coupon) {
                return [
                    'id' => $coupon->id,
                    'code' => $coupon->code,
                    'name' => $coupon->name,
                    'description' => $coupon->description,
                    'discount_type_id' => $coupon->discount_type_id,
                    'discount_type' => $coupon->discount_type,
                    'discount_type_label' => $coupon->discount_type_label,
                    'discount_value' => (float) $coupon->discount_value,
                    'maximum_discount' => $coupon->maximum_discount !== null ? (float) $coupon->maximum_discount : null,
                    'minimum_order_amount' => $coupon->minimum_order_amount !== null ? (float) $coupon->minimum_order_amount : null,
                    'usage_limit' => $coupon->usage_limit !== null ? (int) $coupon->usage_limit : null,
                    'user_usage_limit' => $coupon->user_usage_limit !== null ? (int) $coupon->user_usage_limit : null,
                    'used_count' => (int) $coupon->used_count,
                    'starts_at' => $coupon->starts_at,
                    'expires_at' => $coupon->expires_at,
                    'is_active' => (bool) $coupon->is_active,
                    'created_at' => $coupon->created_at,
                    'updated_at' => $coupon->updated_at,
                ];
            });
    }

    public function getCoupon(int $id): object
    {
        $coupon = DB::table('coupons')
            ->leftJoin('master_types as discount_type','discount_type.id','=','coupons.discount_type_id')
            ->select(
                'coupons.*','discount_type.value as discount_type','discount_type.description as discount_type_label'
            )->where('coupons.id', $id)->first();

        if (!$coupon) {
            abort(404, 'Coupon not found.');
        }

        return $coupon;
    }

    public function update(int $id, array $data): object
    {
        $coupon = DB::table('coupons')
            ->where('id', $id)
            ->first();

        if (!$coupon) {
            abort(404, 'Coupon not found.');
        }

        $code = strtoupper(trim($data['code']));

        $exists = DB::table('coupons')
            ->whereRaw('LOWER(code) = ?', [strtolower($code)])
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => 'This coupon code already exists.',
            ]);
        }

        $discountType = DB::table('master_types')
            ->where('id', $data['discount_type_id'])
            ->where('key', 'discount_type')
            ->first();

        if (!$discountType) {
            throw ValidationException::withMessages([
                'discount_type_id' => 'Invalid discount type.',
            ]);
        }

        if (
            strtolower($discountType->value) === 'percentage'
            && $data['discount_value'] > 100
        ) {
            throw ValidationException::withMessages([
                'discount_value' => 'Percentage discount cannot be greater than 100.',
            ]);
        }

        DB::table('coupons')
            ->where('id', $id)
            ->update([
                'code' => $code,
                'name' => $data['name'] ?? $code,
                'description' => $data['description'] ?? null,
                'discount_type_id' => $data['discount_type_id'],
                'discount_value' => $data['discount_value'],
                'maximum_discount' => $data['maximum_discount'] ?? null,
                'minimum_order_amount' => $data['minimum_order_amount'] ?? null,
                'usage_limit' => $data['usage_limit'] ?? null,
                'user_usage_limit' => $data['user_usage_limit'] ?? null,
                'starts_at' => $data['starts_at'] ?? $coupon->starts_at,
                'expires_at' => $data['expires_at'] ?? null,
                'is_active' => $data['is_active'] ?? $coupon->is_active,
                'updated_at' => now(),
            ]);

        return $this->getCoupon($id);
    }

    public function delete(int $id): bool
    {
        $coupon = DB::table('coupons')
            ->where('id', $id)
            ->first();

        if (!$coupon) {
            abort(404, 'Coupon not found.');
        }

        DB::table('coupons')
            ->where('id', $id)
            ->delete();

        return true;
    }

}
