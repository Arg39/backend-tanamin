<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public function getImage(Request $request, $filename)
    {
        $request->validate([
            'width' => 'nullable|integer|min:50|max:2000',
            'height' => 'nullable|integer|min:50|max:2000',
        ]);

        $path = 'categories/' . $filename;

        if (!Storage::exists($path)) {
            return response()->json(['message' => 'Image not found'], 404);
        }

        $image = Storage::get($path);
        $mimeType = Storage::mimeType($path);

        return response($image, 200)->header('Content-Type', $mimeType);
    }
}  