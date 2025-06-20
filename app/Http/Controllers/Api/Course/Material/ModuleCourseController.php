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
    public function index($courseId)
    {
        try {
            // Ambil semua module untuk course terkait, urutkan berdasarkan order
            $modules = \App\Models\ModuleCourse::where('course_id', $courseId)
                ->orderBy('order', 'asc')
                ->get();

            // Bentuk array hasil sesuai format yang diminta
            $result = [];
            foreach ($modules as $module) {
                $lessons = $module->lessons()
                    ->orderBy('order', 'asc')
                    ->get()
                    ->map(function ($lesson) {
                        return [
                            'id' => $lesson->id,
                            'type' => $lesson->type,
                            'title' => $lesson->title,
                        ];
                    })
                    ->toArray();

                $result[] = [
                    'id' => $module->id,
                    'title' => $module->title,
                    'lessons' => $lessons,
                ];
            }

            return new \App\Http\Resources\PostResource(true, 'Modules fetched successfully', $result);
        } catch (\Exception $e) {
            return new \App\Http\Resources\PostResource(false, 'Failed to fetch modules', $e->getMessage());
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
                ->filter(fn ($mod) => $mod->id !== $movedModule->id)
                ->values()
                ->all();

            $newOrder = min($request->order, count($modules));
            array_splice($modules, $newOrder, 0, [$movedModule]);

            foreach ($modules as $index => $module) {
                $module->update(['order' => $index]);
            }

            return new PostResource(true, 'Module order updated successfully', (new ModuleCourseResource($movedModule))->resolve(request()));
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to update module order', $e->getMessage());
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