<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\City;

class SellercityController extends Controller
{
    public function getCitiesBycity()
    {
        $cities = Cache::remember('cities', 300, function () {
            return City::select('id', 'name')->get()->toArray();
        });
        return response()->json([
            'status' => true,
            'data' => $cities,
        ]);
    }
}
