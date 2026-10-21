<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ShippingZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShippingZoneController extends Controller
{
    public function __construct(
        private ShippingZoneService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'state_id' => 'nullable|integer|min:1',
            'city_id' => 'nullable|integer|min:1',
            'is_nationwide' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipping zones fetched successfully.',
            'data' => $this->service->index($request),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Shipping zone fetched successfully.',
            'data' => $this->service->show($id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:shipping_zones,code',
            'description' => 'nullable|string',
            'state_id' => 'nullable|integer|exists:states,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'is_nationwide' => 'required|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        if (
            !$data['is_nationwide']
            && !empty($data['city_id'])
            && empty($data['state_id'])
        ) {
            throw ValidationException::withMessages([
                'state_id' => [
                    'State is required when a city is selected.',
                ],
            ]);
        }

        if (!empty($data['city_id']) && !empty($data['state_id'])) {
            $cityBelongsToState = \DB::table('cities')
                ->where('id', $data['city_id'])
                ->where('state_id', $data['state_id'])
                ->exists();

            if (!$cityBelongsToState) {
                throw ValidationException::withMessages([
                    'city_id' => [
                        'Selected city does not belong to the selected state.',
                    ],
                ]);
            }
        }

        $zone = $this->service->store($data);

        return response()->json([
            'success' => true,
            'message' => 'Shipping zone created successfully.',
            'data' => $zone,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('shipping_zones', 'code')->ignore($id),
            ],
            'description' => 'sometimes|nullable|string',
            'state_id' => 'sometimes|nullable|integer|exists:states,id',
            'city_id' => 'sometimes|nullable|integer|exists:cities,id',
            'is_nationwide' => 'sometimes|required|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $current = $this->service->show($id);

        $stateId = array_key_exists('state_id', $data)
            ? $data['state_id']
            : $current->state_id;

        $cityId = array_key_exists('city_id', $data)
            ? $data['city_id']
            : $current->city_id;

        $isNationwide = $data['is_nationwide']
            ?? $current->is_nationwide;

        if (!$isNationwide && $cityId && !$stateId) {
            throw ValidationException::withMessages([
                'state_id' => [
                    'State is required when a city is selected.',
                ],
            ]);
        }

        if (!$isNationwide && $cityId && $stateId) {
            $cityBelongsToState = \DB::table('cities')
                ->where('id', $cityId)
                ->where('state_id', $stateId)
                ->exists();

            if (!$cityBelongsToState) {
                throw ValidationException::withMessages([
                    'city_id' => [
                        'Selected city does not belong to the selected state.',
                    ],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Shipping zone updated successfully.',
            'data' => $this->service->update($id, $data),
        ]);
    }

    public function updateStatus(
        Request $request,
        int $id
    ): JsonResponse {
        $data = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shipping zone status updated successfully.',
            'data' => $this->service->updateStatus(
                $id,
                $data['is_active']
            ),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->destroy($id);

        return response()->json([
            'success' => true,
            'message' => 'Shipping zone deleted successfully.',
        ]);
    }
}
