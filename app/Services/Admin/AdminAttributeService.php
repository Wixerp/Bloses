<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminAttributeService
{
    public function index(array $filters = []): array
    {
        $query = DB::table('attributes as a')
            ->select([
                'a.id',
                'a.name',
                'a.slug',
                'a.description',
                'a.is_active',
                'a.sort_order',
                'a.created_at',
            ])
            ->selectSub(
                DB::table('attribute_values as av')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('av.attribute_id', 'a.id'),
                'values_count'
            );

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('a.is_active', $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('a.name', 'ILIKE', "%{$search}%")
                    ->orWhere('a.slug', 'ILIKE', "%{$search}%");
            });
        }

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $result = $query->orderBy('a.sort_order')
            ->orderBy('a.id')
            ->paginate($perPage);

        return [
            'items' => $result->items(),
            'pagination' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
        ];
    }

    public function show(int $id): array
    {
        $attribute = DB::table('attributes')->where('id', $id)->first();

        if (!$attribute) {
            abort(404, 'Attribute not found.');
        }

        $attribute->values = DB::table('attribute_values')
            ->where('attribute_id', $id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return ['attribute' => $attribute];
    }

    public function store(array $data): array
    {
        $now = now();
        $slug = $this->uniqueSlug(
            'attributes',
            $data['slug'] ?? $data['name']
        );

        $id = DB::table('attributes')->insertGetId([
            'name' => trim($data['name']),
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->show($id);
    }

    public function update(int $id, array $data): array
    {
        $attribute = DB::table('attributes')->where('id', $id)->first();

        if (!$attribute) {
            abort(404, 'Attribute not found.');
        }

        $updates = [
            'updated_at' => now(),
        ];

        if (array_key_exists('name', $data)) {
            $updates['name'] = trim($data['name']);
        }

        if (array_key_exists('slug', $data) || array_key_exists('name', $data)) {
            $base = $data['slug'] ?? $data['name'] ?? $attribute->slug;

            $updates['slug'] = $this->uniqueSlug(
                'attributes',
                $base,
                $id
            );
        }

        foreach (['description', 'is_active', 'sort_order'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        DB::table('attributes')->where('id', $id)->update($updates);

        return $this->show($id);
    }

    public function destroy(int $id): array
    {
        $attribute = DB::table('attributes')->where('id', $id)->first();

        if (!$attribute) {
            abort(404, 'Attribute not found.');
        }

        $hasValues = DB::table('attribute_values')
            ->where('attribute_id', $id)
            ->exists();

        if ($hasValues) {
            throw ValidationException::withMessages([
                'attribute' => [
                    'This attribute has values. Remove its values first, after checking variant usage.',
                ],
            ]);
        }

        DB::table('attributes')->where('id', $id)->delete();

        return ['id' => $id, 'message' => 'Attribute deleted successfully.'];
    }

    public function storeValue(int $attributeId, array $data): array
    {
        if (!DB::table('attributes')->where('id', $attributeId)->exists()) {
            abort(404, 'Attribute not found.');
        }

        $id = DB::table('attribute_values')->insertGetId([
            'attribute_id' => $attributeId,
            'value' => trim($data['value']),
            'slug' => $this->uniqueValueSlug(
                $attributeId,
                $data['slug'] ?? $data['value']
            ),
            'color_code' => $data['color_code'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'value' => DB::table('attribute_values')->where('id', $id)->first(),
        ];
    }

    public function updateValue(int $attributeId, int $valueId, array $data): array
    {
        $value = DB::table('attribute_values')
            ->where('id', $valueId)
            ->where('attribute_id', $attributeId)
            ->first();

        if (!$value) {
            abort(404, 'Attribute value not found.');
        }

        $updates = ['updated_at' => now()];

        if (array_key_exists('value', $data)) {
            $updates['value'] = trim($data['value']);
        }

        if (array_key_exists('slug', $data) || array_key_exists('value', $data)) {
            $updates['slug'] = $this->uniqueValueSlug(
                $attributeId,
                $data['slug'] ?? $data['value'] ?? $value->slug,
                $valueId
            );
        }

        foreach (['color_code', 'sort_order', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        DB::table('attribute_values')->where('id', $valueId)->update($updates);

        return [
            'value' => DB::table('attribute_values')->where('id', $valueId)->first(),
        ];
    }

    public function destroyValue(int $attributeId, int $valueId): array
    {
        $value = DB::table('attribute_values')
            ->where('id', $valueId)
            ->where('attribute_id', $attributeId)
            ->first();

        if (!$value) {
            abort(404, 'Attribute value not found.');
        }

        $inUse = DB::table('product_variant_attribute_values')
            ->where('attribute_value_id', $valueId)
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'value' => [
                    'This value is assigned to a product variant and cannot be deleted.',
                ],
            ]);
        }

        DB::table('attribute_values')->where('id', $valueId)->delete();

        return ['id' => $valueId, 'message' => 'Attribute value deleted successfully.'];
    }

    public function variants(array $filters = []): array
    {
        $query = DB::table('product_variants as pv')
            ->leftJoin('products as p', 'p.id', '=', 'pv.product_id')
            ->select([
                'pv.id',
                'pv.product_id',
                'p.name as product_name',
                'pv.name',
                'pv.sku',
                'pv.barcode',
                'pv.price',
                'pv.compare_at_price',
                'pv.cost_price',
                'pv.manage_stock',
                'pv.stock_qty',
                'pv.is_active',
                'pv.is_default',
            ]);

        if (!empty($filters['product_id'])) {
            $query->where('pv.product_id', $filters['product_id']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('p.name', 'ILIKE', "%{$search}%")
                    ->orWhere('pv.name', 'ILIKE', "%{$search}%")
                    ->orWhere('pv.sku', 'ILIKE', "%{$search}%")
                    ->orWhere('pv.barcode', 'ILIKE', "%{$search}%");
            });
        }

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $result = $query->orderByDesc('pv.id')->paginate($perPage);

        return [
            'items' => $result->items(),
            'pagination' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
        ];
    }

    public function variantAttributes(int $variantId): array
    {
        if (!DB::table('product_variants')->where('id', $variantId)->exists()) {
            abort(404, 'Product variant not found.');
        }

        $values = DB::table('product_variant_attribute_values as pvv')
            ->join('attributes as a', 'a.id', '=', 'pvv.attribute_id')
            ->join('attribute_values as av', 'av.id', '=', 'pvv.attribute_value_id')
            ->where('pvv.product_variant_id', $variantId)
            ->select([
                'pvv.id',
                'pvv.product_variant_id',
                'a.id as attribute_id',
                'a.name as attribute_name',
                'av.id as attribute_value_id',
                'av.value',
                'av.color_code',
            ])
            ->orderBy('a.sort_order')
            ->get();

        return ['items' => $values];
    }

    private function uniqueSlug(string $table, string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'attribute';
        $slug = $base;
        $counter = 1;

        while (true) {
            $query = DB::table($table)->where('slug', $slug);

            if ($ignoreId !== null) {
                $query->where('id', '<>', $ignoreId);
            }

            if (!$query->exists()) {
                return $slug;
            }

            $slug = $base . '-' . $counter++;
        }
    }

    private function uniqueValueSlug(
        int $attributeId,
        string $value,
        ?int $ignoreId = null
    ): string {
        $base = Str::slug($value) ?: 'value';
        $slug = $base;
        $counter = 1;

        while (true) {
            $query = DB::table('attribute_values')
                ->where('attribute_id', $attributeId)
                ->where('slug', $slug);

            if ($ignoreId !== null) {
                $query->where('id', '<>', $ignoreId);
            }

            if (!$query->exists()) {
                return $slug;
            }

            $slug = $base . '-' . $counter++;
        }
    }

    public function syncVariantAttributes(
        int $variantId,
        array $attributes
    ): array {
        return DB::transaction(function () use ($variantId, $attributes) {

            $variant = DB::table('product_variants')
                ->where('id', $variantId)
                ->first();

            if (!$variant) {
                throw ValidationException::withMessages([
                    'product_variant_id' => [
                        'Product variant not found.'
                    ]
                ]);
            }

            $attributeIds = [];

            foreach ($attributes as $item) {
                $attributeId = (int) $item['attribute_id'];
                $valueId = (int) $item['attribute_value_id'];

                // Confirm attribute exists and is active.
                $attribute = DB::table('attributes')
                    ->where('id', $attributeId)
                    ->where('is_active', true)
                    ->first();

                if (!$attribute) {
                    throw ValidationException::withMessages([
                        'attributes' => [
                            "Attribute ID {$attributeId} does not exist or is inactive."
                        ]
                    ]);
                }

                // Value must belong to the submitted attribute.
                $value = DB::table('attribute_values')
                    ->where('id', $valueId)
                    ->where('attribute_id', $attributeId)
                    ->where('is_active', true)
                    ->first();

                if (!$value) {
                    throw ValidationException::withMessages([
                        'attributes' => [
                            "Value ID {$valueId} does not belong to attribute ID {$attributeId}, or is inactive."
                        ]
                    ]);
                }

                // A variant can have only one value per attribute.
                if (in_array($attributeId, $attributeIds, true)) {
                    throw ValidationException::withMessages([
                        'attributes' => [
                            "Duplicate attribute ID {$attributeId}."
                        ]
                    ]);
                }

                $attributeIds[] = $attributeId;

                DB::table('product_variant_attribute_values')
                    ->updateOrInsert(
                        [
                            'product_variant_id' => $variantId,
                            'attribute_id' => $attributeId,
                        ],
                        [
                            'attribute_value_id' => $valueId,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
            }

            // Remove previous assignments omitted from this submitted list.
            $query = DB::table('product_variant_attribute_values')
                ->where('product_variant_id', $variantId);

            if (count($attributeIds) > 0) {
                $query->whereNotIn('attribute_id', $attributeIds);
            }

            $query->delete();

            return $this->variantAttributes($variantId);
        });
    }

}
