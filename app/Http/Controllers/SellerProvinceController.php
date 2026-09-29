<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\State;

class SellerProvinceController extends Controller
{
    public function getStateBystate()
    {
        $state = Cache::remember('states', 300, function () {
            return State::select('id', 'name')->get()->toArray();
        });
        // $state = State::select('id', 'name')->get();

        return response()->json([
            'status' => true,
            'data' => $state,
        ]);
    }
}
