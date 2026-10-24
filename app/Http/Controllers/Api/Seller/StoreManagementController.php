<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Services\Seller\StoreManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class StoreManagementController extends Controller
{
    public function __construct(
        private StoreManagementService $service
    ) {}

    public function show()
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Store retrieved successfully.',
                'data' => $this->service->getMyStore(),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve store.',
            ], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $store = $this->service->getMyStore();

            $validator = Validator::make($request->all(), [
                'store_name' => [
                    'sometimes', 'required', 'string', 'max:255',
                ],
                'store_description' => [
                    'sometimes', 'nullable', 'string', 'max:5000',
                ],
                'logo' => [
                    'sometimes', 'nullable', 'url:http,https', 'max:2048',
                ],
                'banner' => [
                    'sometimes', 'nullable', 'url:http,https', 'max:2048',
                ],
                'email' => [
                    'sometimes', 'nullable', 'email', 'max:255',
                ],
                'phone' => [
                    'sometimes', 'nullable', 'string', 'max:30',
                ],
                'website' => [
                    'sometimes', 'nullable', 'url:http,https', 'max:2048',
                ],
                'facebook_url' => [
                    'sometimes', 'nullable', 'url:http,https', 'max:2048',
                ],
                'instagram_url' => [
                    'sometimes', 'nullable', 'url:http,https', 'max:2048',
                ],
                'twitter_url' => [
                    'sometimes', 'nullable', 'url:http,https', 'max:2048',
                ],
                'youtube_url' => [
                    'sometimes', 'nullable', 'url:http,https', 'max:2048',
                ],
            ]);

            $data = $validator->validate();

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provide at least one field to update.',
                ], 422);
            }

            $updated = $this->service->updateMyStore($data);

            return response()->json([
                'success' => true,
                'message' => 'Store updated successfully.',
                'data' => $updated,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update store.',
            ], 500);
        }
    }

    public function publicProfile(string $slug)
    {
        try {
            $store = $this->service->getPublicStore($slug);

            if (!$store) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store not found or inactive.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Public store retrieved successfully.',
                'data' => $store,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to retrieve public store.',
            ], 500);
        }
    }
}
