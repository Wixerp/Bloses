<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\LocationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    public function __construct(
        private LocationService $service
    ) {}

    private function rules(
        Request $request,
        string $resource,
        ?int $id = null
    ): array {
        $partial = $id !== null;

        $rules = [
            'name' => [
                $partial ? 'sometimes' : 'required',
                'string',
                'max:100',
            ],
            'code' => [
                $partial ? 'sometimes' : 'required',
                'string',
                'max:20',
            ],
            'is_active' => 'sometimes|boolean',
        ];

        if ($resource === 'countries') {
            $rules['code'][] = Rule::unique('countries', 'code')
                ->ignore($id);
        } elseif ($resource === 'states') {
            $rules['country_id'] = [
                $partial ? 'sometimes' : 'required',
                'integer',
                'exists:countries,id',
            ];

            $rules['name'][] = Rule::unique('states', 'name')
                ->where('country_id', $request->input('country_id',
                    $id ? \Illuminate\Support\Facades\DB::table('states')
                        ->where('id', $id)->value('country_id') : null))
                ->ignore($id);

            $rules['code'][] = Rule::unique('states', 'code')
                ->where('country_id', $request->input('country_id',
                    $id ? \Illuminate\Support\Facades\DB::table('states')
                        ->where('id', $id)->value('country_id') : null))
                ->ignore($id);
        } elseif ($resource === 'cities') {
            $rules['state_id'] = [
                $partial ? 'sometimes' : 'required',
                'integer',
                'exists:states,id',
            ];

            $rules['name'][] = Rule::unique('cities', 'name')
                ->where('state_id', $request->input('state_id',
                    $id ? \Illuminate\Support\Facades\DB::table('cities')
                        ->where('id', $id)->value('state_id') : null))
                ->ignore($id);

            $rules['code'][] = Rule::unique('cities', 'code')
                ->where('state_id', $request->input('state_id',
                    $id ? \Illuminate\Support\Facades\DB::table('cities')
                        ->where('id', $id)->value('state_id') : null))
                ->ignore($id);
        }

        return $rules;
    }

    private function validResource(string $resource): bool
    {
        return in_array($resource, ['countries', 'states', 'cities'], true);
    }

    private function errorResponse(
        \Throwable $e,
        string $message = 'Operation failed.'
    ): JsonResponse {
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            throw $e;
        }

        report($e);

        return response()->json([
            'success' => false,
            'message' => $message,
        ], 500);
    }

    public function index(Request $request, string $resource): JsonResponse
    {
        if (!$this->validResource($resource)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid resource.',
            ], 404);
        }

        $request->validate([
            'country_id' => 'sometimes|integer|min:1',
            'state_id' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'search' => 'sometimes|string|max:100',
        ]);

        try {
            return response()->json([
                'success' => true,
                'message' => ucfirst($resource) . ' retrieved successfully.',
                'data' => $this->service->index(
                    $resource,
                    $request->all()
                ),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Unable to retrieve records.');
        }
    }

    public function show(string $resource, int $id): JsonResponse
    {
        if (!$this->validResource($resource)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid resource.',
            ], 404);
        }

        try {
            return response()->json([
                'success' => true,
                'message' => 'Record retrieved successfully.',
                'data' => $this->service->show($resource, $id),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Unable to retrieve record.');
        }
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        if (!$this->validResource($resource)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid resource.',
            ], 404);
        }

        $data = $request->validate(
            $this->rules($request, $resource)
        );

        try {
            return response()->json([
                'success' => true,
                'message' => ucfirst(rtrim($resource, 's')) . ' created successfully.',
                'data' => $this->service->create($resource, $data),
            ], 201);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Unable to create record.');
        }
    }

    public function update(
        Request $request,
        string $resource,
        int $id
    ): JsonResponse {
        if (!$this->validResource($resource)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid resource.',
            ], 404);
        }

        $data = $request->validate(
            $this->rules($request, $resource, $id)
        );

        try {
            return response()->json([
                'success' => true,
                'message' => ucfirst(rtrim($resource, 's')) . ' updated successfully.',
                'data' => $this->service->update($resource, $id, $data),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Unable to update record.');
        }
    }

    public function destroy(string $resource, int $id): JsonResponse
    {
        if (!$this->validResource($resource)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid resource.',
            ], 404);
        }

        try {
            $this->service->delete($resource, $id);

            return response()->json([
                'success' => true,
                'message' => 'Record deleted successfully.',
                'data' => null,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Unable to delete record.');
        }
    }
}
