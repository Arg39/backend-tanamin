<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class DetailCourseResource extends JsonResource
{
    public function toArray($request)
    {
        // Diskon: hanya tampil jika aktif dan dalam rentang tanggal
        $discount = null;
        if (
            $this->is_discount_active &&
            $this->discount_start_at &&
            $this->discount_end_at
        ) {
            $now = Carbon::now();
            $start = Carbon::parse($this->discount_start_at);
            $end = Carbon::parse($this->discount_end_at)->endOfDay();
            if ($now->between($start, $end)) {
                $discount = [
                    'type' => $this->discount_type,
                    'value' => $this->discount_value,
                ];
            }
        }

        // Ambil relasi instructor (user dengan role instructor)
        $instructor = $this->whenLoaded('instructor', function () {
            return [
                'id' => $this->instructor->id,
                'photo_profile' => url('storage/' . $this->instructor->photo_profile),
                'name' => $this->instructor->name,
            ];
        }, function () {
            return [
                'id' => $this->instructor->id ?? null,
                'photo_profile' => url('storage/' . $this->instructor->photo_profile ?? null),
                'name' => $this->instructor->name ?? null,
            ];
        });

        // Ambil relasi category
        $category = $this->whenLoaded('category', function () {
            return [
                'id' => $this->category->id,
                'title' => $this->category->title ?? $this->category->name,
            ];
        }, function () {
            return [
                'id' => $this->category->id ?? null,
                'title' => $this->category->title ?? $this->category->name ?? null,
            ];
        });

        // Hitung participants (jumlah user yang mengambil course)
        $participants = method_exists($this, 'participants') 
            ? $this->participants()->count()
            : (property_exists($this, 'participants') ? $this->participants : 0);

        // Hitung rating
        $averageRating = $this->reviews()->avg('rating') ?? 0;
        $totalRatings = $this->reviews()->count();

        // Hitung total materials (jumlah LessonMaterial dari semua lesson di semua module)
        $moduleIds = $this->modules()->pluck('id');
        $lessonIds = \App\Models\LessonCourse::whereIn('module_id', $moduleIds)->pluck('id');

        $totalMaterials = \App\Models\LessonMaterial::whereIn('lesson_id', $lessonIds)->count();
        $totalQuizzes = \App\Models\LessonQuiz::whereIn('lesson_id', $lessonIds)->count();

        // Format updated_at
        $updatedAt = $this->updated_at 
            ? Carbon::parse($this->updated_at)->locale('id')->isoFormat('D MMMM Y')
            : null;

        return [
            'id'       => $this->id,
            'title'    => $this->title,
            'image' => url('storage/' . $this->image),
            'instructor' => $instructor,
            'category' => $category,
            'price' => $this->price,
            'discount' => $discount,
            'level' => $this->level,
            'detail' => $this->detail,
            'participants' => $participants,
            'rating' => [
                'average' => round($averageRating, 2),
                'total' => $totalRatings,
            ],
            'total_materials' => $totalMaterials,
            'total_quizzes' => $totalQuizzes,
            'updated_at' => $updatedAt,
        ];
    }
}