<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\Category;
use App\Traits\FilteringTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class CategoryController extends Controller
{
    use FilteringTrait;

    public function index(Request $request)
    {
        try {
            $filterable = ['name', 'date'];

            $query = Category::query();

            $categories = $this->filterQuery($query, $request, $filterable, ['name']);

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