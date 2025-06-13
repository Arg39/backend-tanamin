<?php

namespace App\Http\Controllers\Api\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttributeResource;
use App\Http\Resources\PostResource;
use App\Models\CourseAttribute;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttributeCourseController extends Controller
{
    // get course attributes by course ID, grouped based on type
    public function index($id)
    {
        if (!$id) {
            return response()->json(['error' => 'Course ID is required'], 400);
        }
        $courseAttributes = CourseAttribute::where('id_course', $id)->get();

        // Group by type, then transform each item with AttributeResource
        $grouped = $courseAttributes->groupBy('type')->map(function ($items) {
            return AttributeResource::collection($items->values());
        });

        return new PostResource(true, 'Data berhasil diambil.', $grouped);
    }
    // add course attribute by type
    public function store(Request $request, $courseId)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:description,prerequisite',
            'content' => 'required|string',
        ]);

        $attributeId = (string) Str::uuid();
        $attribute = CourseAttribute::create([
            'id' => $attributeId,
            'id_course' => $courseId,
            'type' => $validated['type'],
            'content' => $validated['content'],
        ]);
        $typeData = [
            'type' => $attribute->type,
        ];

        return new PostResource(true, 'Atribut kursus berhasil ditambahkan.', (new AttributeResource($attribute))->withExtra($typeData)->resolve(request()));
    }
}