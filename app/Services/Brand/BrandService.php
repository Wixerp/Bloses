<?php

namespace App\Services\Brand;

use App\Models\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class BrandService
{
    public function getBrands()
    {
        return DB::table('brands')->select('id', 'name')->orderBy('name')->get();
    }

}
