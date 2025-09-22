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
            // Ambil dan urutkan berdasarkan type lalu created_at ASC
            $courseAttributes = CourseAttribute::where('course_id', $id)
                ->orderBy('type')
                ->orderBy('created_at', 'asc')
                ->get();

            // Group by type, urutkan dalam group berdasarkan created_at ASC
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

    // add course attribute by type
    public function store(Request $request, $courseId)
    {
        try {
            $validated = $request->validate([
                'type' => 'required|string|in:description,prerequisite',
                'content' => 'required|string',
            ]);

            $attributeId = (string) Str::uuid();
            $attribute = CourseAttribute::create([
                'id' => $attributeId,
                'course_id' => $courseId,
                'type' => $validated['type'],
                'content' => $validated['content'],
            ]);
            $typeData = [
                'type' => $attribute->type,
            ];

            return new PostResource(
                true,
                'Atribut kursus berhasil ditambahkan.',
                (new AttributeResource($attribute))->withExtra($typeData)->resolve(request())
            );
        } catch (\Exception $e) {
            return new PostResource(false, 'Gagal membuat atribut kursus: ' . $e->getMessage(), null);
        }
    }

    // show course attribute by ID
    public function show($courseId, $attributeId)
    {
        try {
            $attribute = CourseAttribute::where('id', $attributeId)
                ->where('course_id', $courseId)
                ->firstOrFail();

            $typeData = [
                'type' => $attribute->type,
            ];

            return new PostResource(
                true,
                'Atribut kursus berhasil ditemukan.',
                (new AttributeResource($attribute))->withExtra($typeData)->resolve(request())
            );
        } catch (\Exception $e) {
            return new PostResource(
                false,
                'Gagal menampilkan atribut kursus: ' . $e->getMessage(),
                null
            );
        }
    }

    // update course attribute by ID
    public function update(Request $request, $courseId, $attributeId)
    {
        try {
            $validated = $request->validate([
                'type' => 'required|string|in:description,prerequisite',
                'content' => 'required|string',
            ]);

            $attribute = CourseAttribute::where('id', $attributeId)
                ->where('course_id', $courseId)
                ->firstOrFail();

            $attribute->update([
                'type' => $validated['type'],
                'content' => $validated['content'],
            ]);

            return new PostResource(
                true,
                'Atribut kursus berhasil diperbarui.',
                (new AttributeResource($attribute))->resolve(request())
            );
        } catch (\Exception $e) {
            return new PostResource(
                false,
                'Gagal memperbarui atribut kursus: ' . $e->getMessage(),
                null
            );
        }
    }

    // delete course attribute by ID
    public function destroy($courseId, $attributeId)
    {
        try {
            $attribute = CourseAttribute::where('id', $attributeId)
                ->where('course_id', $courseId)
                ->firstOrFail();

            $attribute->delete();

            return new PostResource(
                true,
                'Atribut kursus berhasil dihapus.',
                null
            );
        } catch (\Exception $e) {
            return new PostResource(
                false,
                'Gagal menghapus atribut kursus: ' . $e->getMessage(),
                null
            );
        }
    }
}
