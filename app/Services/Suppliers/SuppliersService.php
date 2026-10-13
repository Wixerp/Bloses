<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SuppliersService
{

    public function getAll(array $filters = [])
    {
        $query = Supplier::query();

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('phone', 'ILIKE', "%{$search}%")
                    ->orWhere('address', 'ILIKE', "%{$search}%");
            });
        }

        return $query->orderByDesc('id')->get();
    }

    public function getById(int $id): Supplier
    {
        return Supplier::findOrFail($id);
    }

    public function create(array $data): Supplier
    {
        return Supplier::create($data);
    }

    public function update(int $id, array $data): Supplier
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update($data);
        return $supplier->fresh();
    }

    public function delete(int $id): bool
    {
        $supplier = Supplier::findOrFail($id);

        return (bool) $supplier->delete();
    }
}
