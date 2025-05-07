<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    public function getImage(Request $request, $path, $filename)
    {
        $request->validate([
            'width' => 'nullable|integer|min:50|max:2000',
            'height' => 'nullable|integer|min:50|max:2000',
        ]);
    
        $fullPath = $path . '/' . $filename;
    
        if (!Storage::exists($fullPath)) {
            return response()->json(['message' => 'Image not found'], 404);
        }
    
        $image = Storage::get($fullPath);
        $mimeType = Storage::mimeType($fullPath);
    
        return response($image, 200)->header('Content-Type', $mimeType);
    }
}
