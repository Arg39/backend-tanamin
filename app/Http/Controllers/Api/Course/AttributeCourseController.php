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
        try {
            if (!$id) {
                return response()->json(['error' => 'Course ID is required'], 400);
            }
            $courseAttributes = CourseAttribute::where('course_id', $id)
                ->orderBy('type')
                ->orderBy('created_at', 'asc')
                ->get();

            $grouped = $courseAttributes->groupBy('type')->map(function ($items) {
                return AttributeResource::collection(
                    $items->sortBy('created_at')->values()
                );
            });

            return new PostResource(
                true,
                'Data berhasil diambil.',
                $grouped
            );
        } catch (\Exception $e) {
            return new PostResource(
                false,
                'Gagal menampilkan atribut kursus: ' . $e->getMessage(),
                null
            );
        }
    }

    public function storeOrUpdateAttribute(Request $request, $courseId)
    {
        try {
            $validated = $request->validate([
                'descriptions' => 'array',
                'prerequisites' => 'array',
                'benefits' => 'array',
            ]);

            $types = [
                'description' => $validated['descriptions'] ?? [],
                'prerequisite' => $validated['prerequisites'] ?? [],
                'benefit' => $validated['benefits'] ?? [],
            ];

            $result = [];

            foreach ($types as $type => $content) {
                if (!empty($content)) {
                    $attribute = CourseAttribute::updateOrCreate(
                        [
                            'course_id' => $courseId,
                            'type' => $type,
                        ],
                        [
                            'id' => CourseAttribute::where('course_id', $courseId)->where('type', $type)->value('id') ?? (string) Str::uuid(),
                            'content' => $content,
                        ]
                    );
                    $result[$type] = new AttributeResource($attribute);
                }
            }

            return new PostResource(
                true,
                'Atribut kursus berhasil disimpan/diperbarui.',
                $result
            );
        } catch (\Exception $e) {
            return new PostResource(false, 'Gagal menyimpan atribut kursus: ' . $e->getMessage(), null);
        }
    }
}
