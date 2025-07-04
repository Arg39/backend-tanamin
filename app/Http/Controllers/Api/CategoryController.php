<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $sortBy = $request->input('sortBy', 'name');
            $sortOrder = $request->input('sortOrder', 'asc');
            $perPage = (int) $request->input('perPage', 10);

            $name = $request->input('name');
            $startDate = $request->input('startDate');
            $endDate = $request->input('endDate');

            $query = Category::query();

            if ($name) {
                $query->where('name', 'like', '%' . $name . '%');
            }

            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            // Ambil user dari JWT, jika ada
            $user = null;
            try {
                $user = JWTAuth::parseToken()->authenticate();
            } catch (\Exception $e) {
                $user = null;
            }

            // Jika user tidak login atau student, lakukan shuffling mingguan
            if (!$user || $user->role === 'student') {
                // Ambil semua id kategori
                $allCategories = $query->get();
                $categoryArray = $allCategories->all();

                // Seed mingguan: tahun-minggu
                $now = now();
                $year = $now->year;
                $week = $now->weekOfYear;
                $seed = crc32($year . '-' . $week);

                // Shuffle dengan seed
                srand($seed);
                usort($categoryArray, function($a, $b) {
                    return rand(-1, 1);
                });
                srand(); // reset

                // Paginate manual
                $page = max((int)$request->input('page', 1), 1);
                $offset = ($page - 1) * $perPage;
                $pagedCategories = array_slice($categoryArray, $offset, $perPage);

                // Buat koleksi laravel dari array
                $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                    collect($pagedCategories)->values(),
                    count($categoryArray),
                    $perPage,
                    $page,
                    ['path' => $request->url(), 'query' => $request->query()]
                );

                $paginated->getCollection()->transform(function ($category) {
                    $category->used = $category->courses()->count();
                    return $category;
                });

                return new TableResource(true, 'Categories retrieved successfully', [
                    'data' => $paginated,
                ], 200);
            }

            // Jika admin, sorting biasa
            $categories = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

            $categories->getCollection()->transform(function ($category) {
                $category->used = $category->courses()->count();
                return $category;
            });

            return new TableResource(true, 'Categories retrieved successfully', [
                'data' => $categories,
            ], 200);
        } catch (\Exception $e) {
            return (new ErrorResource(['message' => 'Failed to retrieve categories: ' . $e->getMessage()]))
                ->response()
                ->setStatusCode(500);
        }
    }

    public function store(Request $request)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'admin') {
            return new PostResource(false, 'Unauthorized', null);
        }

        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:categories,name',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $imagePath = $request->file('image') ? $request->file('image')->store('categories', 'public') : null;

            $category = Category::create([
                'name' => $request->name,
                'image' => $imagePath,
            ]);

            return new PostResource(true, 'Category created successfully', $category);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to create category: ' . $e->getMessage(), null);
        }
    }

    public function getCategoryById($id)
    {
        try {
            $category = Category::find($id);

            if (!$category) {
                return new PostResource(false, 'Category not found', null);
            }

            return new PostResource(true, 'Category retrieved successfully', $category);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve category: ' . $e->getMessage(), null);
        }
    }

    public function update(Request $request, $id)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'admin') {
            return new PostResource(false, 'Unauthorized', null);
        }
    
        $category = Category::find($id);
    
        if (!$category) {
            return new PostResource(false, 'Category not found', null);
        }
    
        // Validasi input
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $id,
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
    
        // Update file jika ada
        if ($request->hasFile('image')) {
            // Hapus gambar lama jika ada
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
    
            $imagePath = $request->file('image')->store('categories', 'public');
            $category->image = $imagePath;
        }
    
        // Update nama kategori
        $category->name = $request->input('name');
        $category->save();
    
        return new PostResource(true, 'Category updated successfully', $category);
    }

    public function destroy($id)
    {
        $user = JWTAuth::user();
        if ($user->role !== 'admin') {
            return new PostResource(false, 'Unauthorized', null);
        }

        $category = Category::find($id);

        if (!$category) {
            return new PostResource(false, 'Category not found', null);
        }

        if ($category->image) {
            if (Storage::disk('public')->exists($category->image)) {
                Storage::disk('public')->delete($category->image);
            } else {
                return new PostResource(false, 'Image not found', null);
            }
        }

        $category->delete();

        return new PostResource(true, 'Category deleted successfully', null);
    }

    public function getCategoriesForSelect()
    {
        try {
            $categories = Category::select('id', 'name')->get();

            if ($categories->isEmpty()) {
                return new PostResource(false, 'No categories found', []);
            }

            return new PostResource(true, 'Categories retrieved successfully', $categories);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to retrieve categories: ' . $e->getMessage(), null);
        }
    }
}