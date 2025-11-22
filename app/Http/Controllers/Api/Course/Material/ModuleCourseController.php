<?php

namespace App\Http\Controllers\Api\Course\Material;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModuleCourseResource;
use App\Http\Resources\PostResource;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\ModuleCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class ModuleCourseController extends Controller
{
    // get all modules for a course
    public function index($courseId)
    {
        try {
            $user = JWTAuth::user();
            $completedLessonIds = [];
            if ($user) {
                $completedLessonIds = \App\Models\LessonProgress::where('user_id', $user->id)
                    ->whereNotNull('completed_at')
                    ->pluck('lesson_id')
                    ->toArray();
            }
            $statusCourse = Course::find($courseId)?->status;

            $modules = \App\Models\ModuleCourse::where('course_id', $courseId)
                ->orderBy('order', 'asc')
                ->get();

            $result = [];
            foreach ($modules as $module) {
                $lessons = $module->lessons()
                    ->orderBy('order', 'asc')
                    ->get()
                    ->map(function ($lesson) use ($completedLessonIds) {
                        $lessonArr = [
                            'id' => $lesson->id,
                            'type' => $lesson->type,
                            'title' => $lesson->title,
                        ];
                        if ($lesson->type === 'material') {
                            $material = $lesson->materials()->first();
                            $lessonArr['visible'] = $material ? (bool)$material->visible : false;
                        }
                        $lessonArr['completed'] = in_array($lesson->id, $completedLessonIds);
                        return $lessonArr;
                    })
                    ->toArray();

                $result[] = [
                    'id' => $module->id,
                    'title' => $module->title,
                    'lessons' => $lessons,
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Modules fetched successfully',
                'status_course' => $statusCourse,
                'data' => $result,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch modules',
                'status_course' => null,
                'data' => null,
            ], 500);
        }
    }

    public function indexForStudent($courseId)
    {
        try {
            $user = JWTAuth::user();
            if (!$user) {
                return new PostResource(false, 'Forbidden: User not authenticated', null, 403);
            }

            if ($user->role !== 'student') {
                return new PostResource(false, 'Forbidden: Only students can access this resource', null, 403);
            }

            // Ambil enrollment berdasarkan user_id dan course_id, sekaligus session checkout-nya
            $enrollment = CourseEnrollment::with('checkoutSession')
                ->where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->first();

            if (!$enrollment) {
                return new PostResource(false, 'Forbidden: Enrollment not found', null, 403);
            }

            $accessActive = in_array($enrollment->access_status, ['active', 'completed'], true);
            $checkoutSession = $enrollment->checkoutSession;
            $sessionPaid = $checkoutSession && $checkoutSession->payment_status === 'paid';

            $hasAccess = false;
            switch ($enrollment->payment_type) {
                case 'free':
                    $hasAccess = $accessActive || $sessionPaid;
                    break;
                case 'midtrans':
                    $hasAccess = $sessionPaid || $accessActive;
                    break;
                case 'pending':
                default:
                    $hasAccess = $sessionPaid;
                    break;
            }

            if (!$hasAccess) {
                return new PostResource(false, 'Forbidden: Access denied for this course', null, 403);
            }

            // Fetch completed lesson IDs for this user
            $completedLessonIds = \App\Models\LessonProgress::where('user_id', $user->id)
                ->whereNotNull('completed_at')
                ->pluck('lesson_id')
                ->toArray();

            $modules = \App\Models\ModuleCourse::where('course_id', $courseId)
                ->orderBy('order', 'asc')
                ->get();

            $result = [];
            foreach ($modules as $module) {
                $lessons = $module->lessons()
                    ->orderBy('order', 'asc')
                    ->get()
                    ->map(function ($lesson) use ($completedLessonIds) {
                        $lessonArr = [
                            'id' => $lesson->id,
                            'type' => $lesson->type,
                            'title' => $lesson->title,
                        ];
                        if ($lesson->type === 'material') {
                            $material = $lesson->materials()->first();
                            $lessonArr['visible'] = $material ? (bool)$material->visible : false;
                        }
                        $lessonArr['completed'] = in_array($lesson->id, $completedLessonIds);
                        return $lessonArr;
                    })
                    ->toArray();

                $result[] = [
                    'id' => $module->id,
                    'title' => $module->title,
                    'lessons' => $lessons,
                ];
            }

            return new PostResource(true, 'Modules fetched successfully', $result);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to fetch modules for student', null);
        }
    }


    // get module by id
    public function show($courseId, $moduleId)
    {
        try {
            $module = ModuleCourse::where('course_id', $courseId)->findOrFail($moduleId);
            return new PostResource(true, 'Module found', (new ModuleCourseResource($module))->resolve(request()));
        } catch (\Exception $e) {
            return new PostResource(false, 'Module not found', null);
        }
    }

    // create a new module for a course
    public function store(Request $request, $courseId)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
            ]);

            // Perbarui urutan module jika ada celah
            $this->updateModuleOrder($courseId);

            // Tentukan order untuk module baru
            $order = ModuleCourse::where('course_id', $courseId)->count();

            // Buat module baru
            $moduleId = (string) Str::uuid();
            $module = ModuleCourse::create([
                'id' => $moduleId,
                'course_id' => $courseId,
                'title' => $request->title,
                'order' => $order,
            ]);

            return new PostResource(true, 'Module created successfully', (new ModuleCourseResource($module))->resolve(request()));
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to create module', null);
        }
    }

    // update a module by ID
    public function update(Request $request, $courseId, $moduleId)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'order' => 'nullable|integer|min:0',
            ]);

            $module = ModuleCourse::where('course_id', $courseId)->findOrFail($moduleId);
            $module->update([
                'title' => $request->title,
                'order' => $request->order ?? $module->order,
            ]);

            // Perbarui urutan module setelah update
            $this->updateModuleOrder($courseId);

            return new PostResource(true, 'Module updated successfully', (new ModuleCourseResource($module))->resolve(request()));
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to update module', ['error' => $e->getMessage()]);
        }
    }

    // update module order by drag and drop (single move) WITHOUT courseId
    public function updateByOrder(Request $request)
    {
        try {
            $request->validate([
                'id' => 'required|string',
                'order' => 'required|integer|min:0',
            ]);

            // Find the module to move
            $movedModule = ModuleCourse::findOrFail($request->id);

            // Get all modules in the same course
            $modules = ModuleCourse::where('course_id', $movedModule->course_id)
                ->orderBy('order', 'asc')
                ->get()
                ->filter(fn($mod) => $mod->id !== $movedModule->id)
                ->values()
                ->all();

            $newOrder = min($request->order, count($modules));
            array_splice($modules, $newOrder, 0, [$movedModule]);

            foreach ($modules as $index => $module) {
                $module->update(['order' => $index]);
            }

            return new PostResource(true, 'Module order updated successfully', (new ModuleCourseResource($movedModule))->resolve(request()));
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to update module order', null);
        }
    }

    // delete a module by ID
    public function destroy($courseId, $moduleId)
    {
        try {
            $module = ModuleCourse::where('course_id', $courseId)->findOrFail($moduleId);
            $module->delete();

            // Perbarui urutan module setelah penghapusan
            $this->updateModuleOrder($courseId);

            return new PostResource(true, 'Module deleted successfully', null);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to delete module', null);
        }
    }

    // Update the order of modules after deletion or insertion
    private function updateModuleOrder($courseId)
    {
        $modules = ModuleCourse::where('course_id', $courseId)
            ->orderBy('order', 'asc')
            ->get();

        $currentOrder = 0;
        foreach ($modules as $module) {
            if ($module->order != $currentOrder) {
                $module->update(['order' => $currentOrder]);
            }
            $currentOrder++;
        }
    }
}
