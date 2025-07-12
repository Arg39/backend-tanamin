<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait FilteringTrait
{
    /**
     * Universal filter for Eloquent queries.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @param array $filterable
     * @param array $searchable
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function filterQuery($query, Request $request, $filterable = [], $searchable = [])
    {
        $sortBy = $request->input('sortBy', 'id');
        $sortOrder = $request->input('sortOrder', 'asc');
        $perPage = (int) $request->input('perPage', 10);

        // Search filter
        if ($request->filled('search') && !empty($searchable)) {
            $search = $request->input('search');
            $query->where(function ($q) use ($searchable, $search) {
                foreach ($searchable as $column) {
                    $q->orWhere($column, 'like', '%' . $search . '%');
                }
            });
        }

        // Name filter
        if (in_array('name', $filterable) && $request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        if (in_array('date', $filterable)) {
            $startDate = $request->input('dateStart', $request->input('startDate'));
            $endDate = $request->input('dateEnd', $request->input('endDate'));
        
            if ($endDate && !preg_match('/\d{2}:\d{2}:\d{2}/', $endDate)) {
                $endDate .= ' 23:59:59';
            }
        
            if ($startDate && $endDate) {
                $query->whereBetween('updated_at', [$startDate, $endDate]);
            } else {
                if ($startDate) {
                    $query->whereDate('updated_at', '>=', $startDate);
                }
                if ($endDate) {
                    $query->where('updated_at', '<=', $endDate);
                }
            }
        }

        // Custom filterable columns
        foreach ($filterable as $column) {
            if (!in_array($column, ['name', 'date']) && $request->filled($column)) {
                $query->where($column, $request->input($column));
            }
        }

        // Only allow sorting by allowed columns
        $allowedSorts = array_merge(['id'], $filterable, $searchable, ['created_at']);
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'id';
        }

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }
}