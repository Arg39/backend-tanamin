<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ErrorResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TableResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $sortBy = $request->input('sortBy', 'name');
            $sortOrder = $request->input('sortOrder', 'asc');
            $perPage = (int) $request->input('perPage', 10);

            // Retrieve categories with sorting and pagination
            $categories = Category::orderBy($sortBy, $sortOrder)->paginate($perPage);

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
            ]);

            $category = Category::create([
                'name' => $request->name,
            ]);

            return new PostResource(true, 'Category created successfully', $category);
        } catch (\Exception $e) {
            return new PostResource(false, 'Failed to create category: ' . $e->getMessage(), null);
        }
    }

    public function show($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return new PostResource(false, 'Category not found', null);
        }

        return new PostResource(true, 'Category retrieved successfully', $category);
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

        $request->validate([
            'name' => 'required|string|max:255|unique:category,name,' . $id,
        ]);

        $category->update([
            'name' => $request->name,
        ]);

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

        $category->delete();

        return new PostResource(true, 'Category deleted successfully', null);
    }
}