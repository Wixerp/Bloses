<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Country;

class SellerCountryController extends Controller
{
    public function getcountryBycountry()
    {
        $country = Cache::remember('countries', 300, function () {
            return Country::select('id', 'name')->get()->toArray();
        });
        // $country = Country::select('id', 'name')->get();

        return response()->json([
            'status' => true,
            'data' => $country,
        ]);
    }
}
