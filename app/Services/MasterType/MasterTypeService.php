<?php

namespace App\Services\MasterType;

use App\Models\MasterType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;

class MasterTypeService
{
    public function getByKey(string $key): Collection
    {
        $ids = Cache::remember("master_types_ids:{$key}", 300, function () use ($key) {
                return MasterType::query()->where('key', $key)->orderBy('id')->pluck('id')->toArray();
            }
        );

        return MasterType::query()->whereIn('id', $ids)->orderBy('id')->get();
    }

}
