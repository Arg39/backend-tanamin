<?php

namespace Tests\Unit\Controller\CategoryController;

use App\Http\Controllers\Api\CategoryController;
use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class CategoryUpdateTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        // Ensure deterministic start
        Category::query()->delete();
    }

    public function test_update_unauthorized_non_admin(): void
    {
        // Create a sample category
        $category = Category::create([
            'name' => 'Original Name',
            'image' => null,
        ]);

        // Mock non-admin user
        $nonAdmin = (object) ['role' => 'user'];
        JWTAuth::shouldReceive('user')->andReturn($nonAdmin);

        $request = new Request(['name' => 'New Name']);

        $controller = new CategoryController();
        $result = $controller->update($request, $category->id);

        $response = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Unauthorized', $response['message']);

        // Ensure DB not changed
        $fresh = Category::find($category->id);
        $this->assertEquals('Original Name', $fresh->name);
    }

    public function test_update_not_found_returns_error(): void
    {
        // Ensure no category with id 99999
        Category::query()->delete();

        // Mock admin
        $admin = (object) ['role' => 'admin'];
        JWTAuth::shouldReceive('user')->andReturn($admin);

        $request = new Request(['name' => 'Whatever']);

        $controller = new CategoryController();
        $result = $controller->update($request, 99999); // non-existent id

        $response = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Category not found', $response['message']);
    }

    public function test_update_as_admin_updates_name(): void
    {
        // Create a sample category
        $category = Category::create([
            'name' => 'Old Category',
            'image' => null,
        ]);

        // Mock admin
        $admin = (object) ['role' => 'admin'];
        JWTAuth::shouldReceive('user')->andReturn($admin);

        $newName = 'Updated Category';
        $request = new Request(['name' => $newName]);

        $controller = new CategoryController();
        $result = $controller->update($request, $category->id);

        $response = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Category updated successfully', $response['message']);

        // Data may be under 'data' => model or directly returned; normalize accordingly
        $data = $response['data'] ?? null;

        // Convert object to array if needed
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        $this->assertNotNull($data, 'Expected returned data for updated category');
        $this->assertArrayHasKey('name', $data);
        $this->assertEquals($newName, $data['name']);

        // Ensure DB updated
        $fresh = Category::find($category->id);
        $this->assertEquals($newName, $fresh->name);
    }

    /**
     * Normalize controller response into an associative array.
     */
    private function normalizeControllerResponse($result): array
    {
        // If it's a Laravel resource that has toResponse/toJson
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            $this->assertEquals(200, $httpResponse->getStatusCode());
            return $httpResponse->getData(true);
        } elseif (is_object($result) && method_exists($result, 'response')) {
            $httpResponse = $result->response();
            if (method_exists($httpResponse, 'getStatusCode')) {
                $this->assertEquals(200, $httpResponse->getStatusCode());
            }
            return $httpResponse->getData(true);
        } elseif (is_array($result)) {
            return $result;
        } else {
            $encoded = json_encode($result);
            return json_decode($encoded, true) ?? [];
        }
    }
}
