<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;

class CloudinaryController extends Controller
{
    public function upload(Request $request, CloudinaryService $cloudinaryService)
    {
        $request->validate([
            'image' => 'required|file|image|max:5120',
        ]);

        $result = $cloudinaryService->upload(
            $request->file('image'),
            'marketplace/test'
        );

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded successfully',
            'data' => $result,
        ]);
    }
}
