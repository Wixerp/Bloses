<?php


namespace App\Http\Controllers\Api\Admin\Coupon;

use App\Http\Controllers\Controller;
use App\Services\Admin\Coupon\CouponService;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(
        protected CouponService $couponService
    ) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_type_id' => ['required', 'integer'],
            'discount_value' => ['required', 'numeric', 'min:0',],
            'maximum_discount' => ['nullable','numeric','min:0',],
            'minimum_order_amount' => ['nullable','numeric','min:0',],
            'usage_limit' => ['nullable','integer','min:1',],
            'user_usage_limit' => ['nullable','integer','min:1',],
            'starts_at' => ['nullable','date',],
            'expires_at' => ['nullable','date','after_or_equal:starts_at',],
            'is_active' => ['nullable','boolean',],
        ]);

        $coupon = $this->couponService->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully.',
            'data' => $coupon,
        ], 201);
    }

    public function index(Request $request)
    {
        $coupons = $this->couponService->getCoupons(
            $request->all()
        );

        return response()->json([
            'success' => true,
            'data' => $coupons,
        ]);
    }

    public function show(int $id)
    {
        $coupon = $this->couponService->getCoupon($id);

        return response()->json([
            'success' => true,
            'data' => $coupon,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_type_id' => ['required', 'integer'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'maximum_discount' => ['nullable', 'numeric', 'min:0'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'user_usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable','date','after_or_equal:starts_at',],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $coupon = $this->couponService->update($id, $data);

        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully.',
            'data' => $coupon,
        ]);
    }

    public function destroy(int $id)
    {
        $this->couponService->delete($id);

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully.',
        ]);
    }


}
