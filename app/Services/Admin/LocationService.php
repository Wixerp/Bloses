<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LocationService
{
    private function config(string $resource): array
    {
        return match ($resource) {
            'countries' => [
                'table' => 'countries',
                'parent' => null,
                'parent_table' => null,
            ],
            'states' => [
                'table' => 'states',
                'parent' => 'country_id',
                'parent_table' => 'countries',
            ],
            'cities' => [
                'table' => 'cities',
                'parent' => 'state_id',
                'parent_table' => 'states',
            ],
            default => throw ValidationException::withMessages([
                'resource' => ['Invalid resource.'],
            ]),
        };
    }

    public function index(string $resource, array $filters = [])
    {
        $cfg = $this->config($resource);
        $table = $cfg['table'];

        $query = DB::table($table);

        if ($resource === 'states') {
            $query->leftJoin(
                'countries as co',
                'co.id',
                '=',
                'states.country_id'
            )->select(
                'states.*',
                'co.name as country_name'
            );
        } elseif ($resource === 'cities') {
            $query->leftJoin(
                'states as st',
                'st.id',
                '=',
                'cities.state_id'
            )->leftJoin(
                'countries as co',
                'co.id',
                '=',
                'st.country_id'
            )->select(
                'cities.*',
                'st.name as state_name',
                'st.country_id',
                'co.name as country_name'
            );
        } else {
            $query->select('countries.*');
        }

        if ($resource === 'states' && !empty($filters['country_id'])) {
            $query->where(
                'states.country_id',
                $filters['country_id']
            );
        }

        if ($resource === 'cities' && !empty($filters['state_id'])) {
            $query->where(
                'cities.state_id',
                $filters['state_id']
            );
        }

        if ($resource === 'cities' && !empty($filters['country_id'])) {
            $query->where(
                'st.country_id',
                $filters['country_id']
            );
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where(
                "$table.is_active",
                $filters['is_active']
            );
        }

        if (!empty($filters['search'])) {
            $query->where(
                "$table.name",
                'ILIKE',
                '%' . trim($filters['search']) . '%'
            );
        }

        return $query->orderBy("$table.name")->get();
    }

    public function show(string $resource, int $id)
    {
        $cfg = $this->config($resource);

        $record = DB::table($cfg['table'])
            ->where('id', $id)
            ->first();

        if (!$record) {
            throw ValidationException::withMessages([
                'id' => ['Record not found.'],
            ]);
        }

        return $record;
    }

    public function create(string $resource, array $data)
    {
        $cfg = $this->config($resource);
        $table = $cfg['table'];

        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table($table)->insertGetId($data);

        return DB::table($table)->where('id', $id)->first();
    }

    public function update(string $resource, int $id, array $data)
    {
        $cfg = $this->config($resource);
        $table = $cfg['table'];

        $this->show($resource, $id);

        if (empty($data)) {
            throw ValidationException::withMessages([
                'data' => ['At least one field is required.'],
            ]);
        }

        $data['updated_at'] = now();

        DB::table($table)
            ->where('id', $id)
            ->update($data);

        return DB::table($table)->where('id', $id)->first();
    }

    public function delete(string $resource, int $id): void
    {
        $cfg = $this->config($resource);
        $table = $cfg['table'];

        $this->show($resource, $id);

        if ($resource === 'countries' &&
            DB::table('states')->where('country_id', $id)->exists()) {
            throw ValidationException::withMessages([
                'id' => ['This country has states. Delete its states first.'],
            ]);
        }

        if ($resource === 'states' &&
            DB::table('cities')->where('state_id', $id)->exists()) {
            throw ValidationException::withMessages([
                'id' => ['This state has cities. Delete its cities first.'],
            ]);
        }

        DB::table($table)->where('id', $id)->delete();
    }
}
