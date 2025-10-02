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
        if ($this->is_discount_active) {
            $now = Carbon::now();
            $start = $this->discount_start_at ? Carbon::parse($this->discount_start_at) : null;
            $end = $this->discount_end_at ? Carbon::parse($this->discount_end_at)->endOfDay() : null;

            $isActive = false;
            if ($start && $end) {
                $isActive = $now->between($start, $end);
            } elseif ($start && !$end) {
                $isActive = $now->greaterThanOrEqualTo($start);
            } elseif (!$start && $end) {
                $isActive = $now->lessThanOrEqualTo($end);
            } elseif (!$start && !$end) {
                $isActive = true;
            }

            if ($isActive) {
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
                'photo_profile' => $this->instructor->photo_profile
                    ? url('storage/' . $this->instructor->photo_profile)
                    : null,
                'name' => $this->instructor->full_name,
            ];
        }, function () {
            return [
                'id' => $this->instructor->id ?? null,
                'photo_profile' => !empty($this->instructor->photo_profile)
                    ? url('storage/' . $this->instructor->photo_profile)
                    : null,
                'name' => $this->instructor->full_name ?? null,
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

        $inCart = false;
        if (isset($this->in_cart)) {
            $inCart = $this->in_cart;
        } elseif ($request->has('in_cart')) {
            $inCart = $request->get('in_cart');
        }

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
                'average' => round((float)$averageRating, 2),
                'total' => $totalRatings,
            ],
            'total_materials' => $totalMaterials,
            'total_quizzes' => $totalQuizzes,
            'updated_at' => $updatedAt,
            'in_cart' => $inCart,
        ];
    }
}
