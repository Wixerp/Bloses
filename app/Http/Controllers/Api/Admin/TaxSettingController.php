<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\TaxSettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class TaxSettingController extends Controller
{
    public function __construct(
        private TaxSettingService $service
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'country_id' => 'nullable|integer|exists:countries,id',
            'state_id' => 'nullable|integer|exists:states,id',
            'is_active' => 'sometimes|boolean',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tax settings retrieved successfully.',
            'data' => $this->service->index($filters),
        ]);
    }

    public function show(int $id)
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Tax setting retrieved successfully.',
                'data' => $this->service->show($id),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'required|string|max:50|unique:tax_rates,code',
            'country_id' => 'nullable|integer|exists:countries,id',
            'state_id' => 'nullable|integer|exists:states,id',
            'city_id' => 'nullable|integer|exists:cities,id',
            'rate' => 'required|numeric|min:0|max:100',
            'tax_type' => [
                'sometimes',
                'string',
                Rule::in(['sales_tax', 'vat', 'gst', 'other']),
            ],
            'is_inclusive' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $this->validateLocation($data);

        try {
            return response()->json([
                'success' => true,
                'message' => 'Tax setting created successfully.',
                'data' => $this->service->create($data),
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create tax setting.',
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:150',
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('tax_rates', 'code')->ignore($id),
            ],
            'country_id' => 'sometimes|nullable|integer|exists:countries,id',
            'state_id' => 'sometimes|nullable|integer|exists:states,id',
            'city_id' => 'sometimes|nullable|integer|exists:cities,id',
            'rate' => 'sometimes|required|numeric|min:0|max:100',
            'tax_type' => [
                'sometimes',
                'string',
                Rule::in(['sales_tax', 'vat', 'gst', 'other']),
            ],
            'is_inclusive' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $current = (array) $this->service->show($id);
            $this->validateLocation(array_merge($current, $data));

            return response()->json([
                'success' => true,
                'message' => 'Tax setting updated successfully.',
                'data' => $this->service->update($id, $data),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update tax setting.',
            ], 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->service->delete($id);

            return response()->json([
                'success' => true,
                'message' => 'Tax setting deleted successfully.',
                'data' => null,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to delete tax setting.',
            ], 500);
        }
    }

    private function validateLocation(array $data): void
    {
        $countryId = $data['country_id'] ?? null;
        $stateId = $data['state_id'] ?? null;
        $cityId = $data['city_id'] ?? null;

        if ($cityId && !$stateId) {
            throw ValidationException::withMessages([
                'state_id' => ['A state is required when a city is selected.'],
            ]);
        }

        if ($stateId) {
            $stateQuery = DB::table('states')->where('id', $stateId);

            if ($countryId) {
                $stateQuery->where('country_id', $countryId);
            } else {
                throw ValidationException::withMessages([
                    'country_id' => ['A country is required when a state is selected.'],
                ]);
            }

            if (!$stateQuery->exists()) {
                throw ValidationException::withMessages([
                    'state_id' => ['The selected state does not belong to this country.'],
                ]);
            }
        }

        if ($cityId) {
            $cityExists = DB::table('cities')
                ->where('id', $cityId)
                ->where('state_id', $stateId)
                ->exists();

            if (!$cityExists) {
                throw ValidationException::withMessages([
                    'city_id' => ['The selected city does not belong to this state.'],
                ]);
            }
        }
    }
}
