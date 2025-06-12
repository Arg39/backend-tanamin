<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait CourseFilterTrait
{
    /**
     * Filter courses for all or by instructor.
     *
     * @param Request $request
     * @param int|null $instructorId
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function filterCourses(Request $request, $instructorId = null)
    {
        $sortBy = $request->input('sortBy', 'title');
        $sortOrder = $request->input('sortOrder', 'asc');
        $perPage = (int) $request->input('perPage', 10);
        $search = $request->input('search');
        $category = $request->input('category');
        $dateStart = $request->input('dateStart');
        $dateEnd = $request->input('dateEnd');

        $query = \App\Models\Course::with(['category:id,name', 'instructor:id,first_name,last_name'])
            ->select(['id', 'id_category', 'id_instructor', 'title', 'price', 'level', 'image', 'status', 'detail', 'created_at', 'updated_at']);

        if ($instructorId) {
            $query->where('id_instructor', $instructorId);
        }
        if ($search) {
            $query->search($search);
        }
        if ($category) {
            $query->category($category);
        }
        if ($dateStart && $dateEnd) {
            $query->whereBetween('created_at', [$dateStart, $dateEnd]);
        } else {
            if ($dateStart) {
                $query->whereDate('created_at', '>=', $dateStart);
            }
            if ($dateEnd) {
                $query->whereDate('created_at', '<=', $dateEnd);
            }
        }

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }
}