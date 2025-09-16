<?php

namespace App\Http\Controllers\Api\Material;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LessonMaterial;
use App\Http\Resources\PostResource;

class MaterialCourseController extends Controller
{
    public function showMaterial($materialId)
    {
        $material = LessonMaterial::find($materialId);

        if (!$material) {
            return new PostResource(false, 'Material not found', null);
        }

        $data = [
            'content' => $material->content,
        ];

        return new PostResource(true, 'Material fetched successfully', $data);
    }
}
