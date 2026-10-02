<?php

namespace App\Http\Controllers\API\Master;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Models\MasterType;

class MasterTypeController extends Controller
{
    public function getkeBymaster($key)
    {
        $masterTypes = Cache::remember("master_types_{$key}", 300, function () use ($key) {
            return MasterType::select('id', 'description as value')
                ->where('key', $key)
                ->get()
                ->toArray();
        });

        return response()->json([
            'status' => true,
            'data' => $masterTypes,
        ]);
    }

}
