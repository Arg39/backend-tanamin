<?php

namespace Tests\Unit\Controller\CategoryController;

use App\Http\Controllers\Api\CategoryController;
use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class CategoryDestroyTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        // Ensure deterministic start
        Category::query()->delete();
    }

    public function test_destroy_unauthorized_non_admin(): void
    {
        $category = Category::create([
            'name' => 'To be kept',
            'image' => null,
        ]);

        $nonAdmin = (object) ['role' => 'user'];
        JWTAuth::shouldReceive('user')->andReturn($nonAdmin);

        $controller = new CategoryController();
        $result = $controller->destroy($category->id);

        $response = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Unauthorized', $response['message']);

        // Ensure DB not changed
        $fresh = Category::find($category->id);
        $this->assertNotNull($fresh);
        $this->assertEquals('To be kept', $fresh->name);
    }

    public function test_destroy_not_found_returns_error(): void
    {
        Category::query()->delete();

        $admin = (object) ['role' => 'admin'];
        JWTAuth::shouldReceive('user')->andReturn($admin);

        $controller = new CategoryController();
        $result = $controller->destroy(99999);

        $response = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Category not found', $response['message']);
    }

    public function test_destroy_as_admin_deletes_category_without_image(): void
    {
        $category = Category::create([
            'name' => 'To be deleted',
            'image' => null,
        ]);

        $admin = (object) ['role' => 'admin'];
        JWTAuth::shouldReceive('user')->andReturn($admin);

        $controller = new CategoryController();
        $result = $controller->destroy($category->id);

        $response = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Category deleted successfully', $response['message']);

        $this->assertNull(Category::find($category->id));
    }

    public function test_destroy_as_admin_with_image_file_deleted(): void
    {
        Storage::fake('public');
        $path = 'categories/test.jpg';
        Storage::disk('public')->put($path, 'dummy');

        $category = Category::create([
            'name' => 'With Image',
            'image' => $path,
        ]);

        $this->assertTrue(Storage::disk('public')->exists($path), 'Precondition: file should exist');

        $admin = (object) ['role' => 'admin'];
        JWTAuth::shouldReceive('user')->andReturn($admin);

        $controller = new CategoryController();
        $result = $controller->destroy($category->id);

        $response = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Category deleted successfully', $response['message']);

        $this->assertFalse(Storage::disk('public')->exists($path));
        $this->assertNull(Category::find($category->id));
    }

    public function test_destroy_image_missing_returns_error(): void
    {
        // Do NOT create the file in storage
        $path = 'categories/missing.jpg';

        $category = Category::create([
            'name' => 'With Missing Image',
            'image' => $path,
        ]);

        $this->assertFalse(Storage::disk('public')->exists($path), 'Precondition: file should NOT exist');

        $admin = (object) ['role' => 'admin'];
        JWTAuth::shouldReceive('user')->andReturn($admin);

        $controller = new CategoryController();
        $result = $controller->destroy($category->id);

        $response = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Image not found', $response['message']);

        // Ensure DB not deleted when image missing
        $this->assertNotNull(Category::find($category->id));
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
