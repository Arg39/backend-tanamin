<?php

namespace Tests\Unit\Controller\CategoryController;

use App\Http\Controllers\Api\CategoryController;
use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class StoreCategoryTest extends TestCase
{
    use DatabaseTransactions;

    private function resolveResponseData($response, Request $request)
    {
        if (is_array($response)) {
            return $response;
        }

        if ($response instanceof JsonResponse) {
            return $response->getData(true);
        }

        if (is_object($response) && method_exists($response, 'toResponse')) {
            $httpResponse = $response->toResponse($request);
            if ($httpResponse instanceof JsonResponse) {
                return $httpResponse->getData(true);
            }
            if (method_exists($httpResponse, 'getData')) {
                return $httpResponse->getData(true);
            }
        }

        if (is_object($response) && method_exists($response, 'getData')) {
            return $response->getData(true);
        }

        throw new \RuntimeException('Unable to resolve response data in test. Response type: ' . gettype($response));
    }

    public function test_store_creates_category_successfully()
    {
        Storage::fake('public');

        JWTAuth::shouldReceive('user')->andReturn((object) ['role' => 'admin']);

        $file = UploadedFile::fake()->image('category.jpg');

        $request = Request::create('/api/categories', 'POST', ['name' => 'New Category']);
        $request->files->set('image', $file);

        $controller = new CategoryController();
        $response = $controller->store($request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertEquals('Category created successfully', $responseData['message']);
        $this->assertDatabaseHas('categories', ['name' => 'New Category']);
        $this->assertNotEmpty($responseData['data']);
        $categoryName = is_array($responseData['data']) ? ($responseData['data']['name'] ?? null) : ($responseData['data']->name ?? null);
        $this->assertEquals('New Category', $categoryName);
    }

    public function test_store_returns_unauthorized_for_non_admin()
    {
        JWTAuth::shouldReceive('user')->andReturn((object) ['role' => 'user']);

        $request = Request::create('/api/categories', 'POST', ['name' => 'Should Not Create']);

        $controller = new CategoryController();
        $response = $controller->store($request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertEquals('Unauthorized', $responseData['message']);
        $this->assertDatabaseMissing('categories', ['name' => 'Should Not Create']);
    }

    public function test_store_validation_fails_missing_name()
    {
        JWTAuth::shouldReceive('user')->andReturn((object) ['role' => 'admin']);

        $request = Request::create('/api/categories', 'POST', []);

        $controller = new CategoryController();
        $response = $controller->store($request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertStringContainsStringIgnoringCase('Failed to create category', $responseData['message']);
        $this->assertDatabaseMissing('categories', ['name' => null]);
    }
}
