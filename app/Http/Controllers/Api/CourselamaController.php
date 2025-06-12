<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\Course;
use App\Models\CourseDescription;
use App\Models\CoursePrerequisite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class CourselamaController extends Controller
{

    public function getDetailCourse($tab, $id)
    {
        try {
            $user = JWTAuth::user();
            if ($user->role !== 'instructor') {
                return new PostResource(false, 'Unauthorized', null);
            }

            if ($tab === 'overview') {
                $course = Course::with(['category', 'instructor'])
                    ->where('id', $id)
                    ->where('id_instructor', $user->id)
                    ->firstOrFail();

                $data = [
                    'id' => $course->id,
                    'title' => $course->title,
                    'category' => $course->category ? [
                        'id' => $course->category->id,
                        'name' => $course->category->name,
                    ] : null,
                    'instructor' => $course->instructor ? [
                        'id' => $course->instructor->id,
                        'full_name' => trim($course->instructor->first_name . ' ' . $course->instructor->last_name),
                    ] : null,
                    'level' => $course->level,
                    'price' => $course->price,
                    'image' => $course->image ? asset('storage/' . $course->image) : null,
                    'detail' => $course->detail,
                    'status' => $course->status,
                    'updated_at' => $course->updated_at,
                    'created_at' => $course->created_at,
                ];

                return new PostResource(true, 'Course retrieved successfully', $data);
            } else if ($tab === 'persyaratan') {
                $prerequisites = CoursePrerequisite::where('id_course', $id)->get(['id', 'content']);
                $data = $prerequisites->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'prerequisite' => $item->content,
                    ];
                });
                return new TableResource(true, 'Prerequisite retrieved successfully', [
                    'data' => $data,
                ], 200);
            } else if ($tab === 'deskripsi') {
                $descriptions = CourseDescription::where('id_course', $id)->get(['id', 'content']);
                $data = $descriptions->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'description' => $item->content,
                    ];
                });
                return new TableResource(true, 'Description retrieved successfully', [
                    'data' => $data,
                ], 200);
            }
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve course: ' . $e->getMessage(), null);
        }
    }

    public function addCourseInfo(Request $request, $id)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:255',
            'type' => 'required|in:prerequisite,description',
        ]);

        // Cek course milik instructor
        $course = Course::where('id', $id)
            ->first();

        if (!$course) {
            return new PostResource(false, 'Course not found or not owned by instructor', null);
        }

        $uuid = (string) Str::uuid();
        if ($validated['type'] === 'prerequisite') {
            $prereq = CoursePrerequisite::create([
                'id' => $uuid,
                'id_course' => $course->id,
                'content' => $validated['content'],
            ]);
            return new PostResource(true, 'Prerequisite added successfully', [
                'id' => $prereq->id,
                'prerequisite' => $prereq->content,
            ]);
        } else {
            $desc = CourseDescription::create([
                'id' => $uuid,
                'id_course' => $course->id, // FIX: add this line
                'content' => $validated['content'],
            ]);
            return new PostResource(true, 'Description added successfully', [
                'id' => $desc->id,
                'description' => $desc->content,
            ]);
        }
    }

    public function getInstructorCourseInfo($id)
    {
        try {
            $course = Course::with([
                    'descriptions' => function ($q) {
                        $q->orderBy('created_at', 'asc');
                    },
                    'prerequisites' => function ($q) {
                        $q->orderBy('created_at', 'asc');
                    }
                ])
                ->where('id', $id)
                ->firstOrFail();

            $prerequisites = $course->prerequisites->map(function ($pre) {
                return [
                    'id' => $pre->id,
                    'content' => $pre->content,
                ];
            });
            $descriptions = $course->descriptions->map(function ($desc) {
                return [
                    'id' => $desc->id,
                    'content' => $desc->content,
                ];
            });

            return new PostResource(true, 'Course info retrieved successfully', [
                'prerequisites' => $prerequisites,
                'descriptions' => $descriptions,
            ]);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve course info: ' . $e->getMessage(), null);
        }
    }

    public function updateInstructorCourseInfo(Request $request, $id, $id_info)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:255',
            'type' => 'required|in:prerequisite,description',
        ]);

        // Cek course milik instructor
        $course = Course::where('id', $id)->first();
        if (!$course) {
            return new PostResource(false, 'Course not found or not owned by instructor', null);
        }

        if ($validated['type'] === 'prerequisite') {
            $info = CoursePrerequisite::where('id', $id_info)->where('id_course', $course->id)->first();
            if (!$info) {
                return new PostResource(false, 'Prerequisite not found', null);
            }
            $info->content = $validated['content'];
            $info->save();
            return new PostResource(true, 'Prerequisite updated successfully', [
                'id' => $info->id,
                'prerequisite' => $info->content,
            ]);
        } else {
            $info = CourseDescription::where('id', $id_info)->where('id_course', $course->id)->first();
            if (!$info) {
                return new PostResource(false, 'Description not found', null);
            }
            $info->content = $validated['content'];
            $info->save();
            return new PostResource(true, 'Description updated successfully', [
                'id' => $info->id,
                'description' => $info->content,
            ]);
        }
    }

    public function deleteInstructorCourseInfo(Request $request, $id, $id_info)
    {
        $validated = $request->validate([
            'type' => 'required|in:prerequisite,description',
        ]);

        // Cek course milik instructor
        $course = Course::where('id', $id)->first();
        if (!$course) {
            return new PostResource(false, 'Course not found or not owned by instructor', null);
        }

        if ($validated['type'] === 'prerequisite') {
            $info = CoursePrerequisite::where('id', $id_info)->where('id_course', $course->id)->first();
            if (!$info) {
                return new PostResource(false, 'Prerequisite not found', null);
            }
            $info->delete();
            return new PostResource(true, 'Prerequisite deleted successfully', null);
        } else {
            $info = CourseDescription::where('id', $id_info)->where('id_course', $course->id)->first();
            if (!$info) {
                return new PostResource(false, 'Description not found', null);
            }
            $info->delete();
            return new PostResource(true, 'Description deleted successfully', null);
        }
    }
}