<?php

namespace App\Http\Controllers\Api\Course\Material;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModuleCourseResource;
use App\Http\Resources\PostResource;
use App\Models\ModuleCourse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ModuleCourseController extends Controller
{
    // get all modules for a course
    public function index(Request $request, $courseId)
    {
        try {
            $modules = ModuleCourse::where('course_id', $courseId)
                ->orderBy('order', 'asc')
                ->get();

            return new PostResource(true, 'Modules fetched successfully', [
                'data' => ModuleCourseResource::collection($modules),
            ]);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to fetch modules', $e->getMessage());
        }
    }

    // create a new module for a course
    public function store(Request $request, $courseId)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'order' => 'nullable|integer|min:0',
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
            return new PostResource(false, 'Failed to create module', $e->getMessage());
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
            return new PostResource(false, 'Failed to update module', $e->getMessage());
        }
    }

    // update by order
    public function updateByOrder(Request $request, $courseId)
    {
        try {
            $request->validate([
                'id' => 'required|array|min:1',
            ]);

            $moduleIds = $request->input('id');
            if (count($moduleIds) < 1) {
                return new PostResource(false, 'At least one module ID is required', null);
            }

            foreach ($moduleIds as $index => $moduleId) {
                $module = ModuleCourse::where('course_id', $courseId)->findOrFail($moduleId);
                $module->update(['order' => $index]);
            }

            $modules = ModuleCourse::where('course_id', $courseId)
                ->orderBy('order', 'asc')
                ->get();

            return new PostResource(true, 'Modules reordered successfully', (new ModuleCourseResource($module))->resolve(request()));
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to reorder modules', $e->getMessage());
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
            return new PostResource(false, 'Failed to delete module', $e->getMessage());
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