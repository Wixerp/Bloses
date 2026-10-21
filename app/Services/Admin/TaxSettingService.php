<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxSettingService
{
    public function index(array $filters = [])
    {
        $query = DB::table('tax_rates as t')
            ->leftJoin('countries as co', 'co.id', '=', 't.country_id')
            ->leftJoin('states as st', 'st.id', '=', 't.state_id')
            ->leftJoin('cities as ci', 'ci.id', '=', 't.city_id')
            ->select(
                't.*',
                'co.name as country_name',
                'st.name as state_name',
                'ci.name as city_name'
            );

        if (isset($filters['is_active'])) {
            $query->where('t.is_active', $filters['is_active']);
        }

        if (!empty($filters['country_id'])) {
            $query->where('t.country_id', $filters['country_id']);
        }

        if (!empty($filters['state_id'])) {
            $query->where('t.state_id', $filters['state_id']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('t.name', 'ILIKE', "%{$search}%")
                    ->orWhere('t.code', 'ILIKE', "%{$search}%");
            });
        }

        return $query->orderByDesc('t.id')->get();
    }

    public function show(int $id)
    {
        $tax = DB::table('tax_rates')->where('id', $id)->first();

        if (!$tax) {
            throw ValidationException::withMessages([
                'id' => ['Tax setting not found.'],
            ]);
        }

        return $tax;
    }

    public function create(array $data)
    {
        $id = DB::table('tax_rates')->insertGetId([
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'country_id' => $data['country_id'] ?? null,
            'state_id' => $data['state_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'rate' => $data['rate'],
            'tax_type' => $data['tax_type'] ?? 'sales_tax',
            'is_inclusive' => $data['is_inclusive'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->show($id);
    }

    public function update(int $id, array $data)
    {
        $this->show($id);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $data['updated_at'] = now();

        DB::table('tax_rates')->where('id', $id)->update($data);

        return $this->show($id);
    }

    public function delete(int $id): void
    {
        $this->show($id);

        DB::table('tax_rates')->where('id', $id)->delete();
    }
}
