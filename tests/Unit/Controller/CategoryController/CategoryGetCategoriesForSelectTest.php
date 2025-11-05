<?php

namespace Tests\Unit\Controller\CategoryController;

use App\Http\Controllers\Api\CategoryController;
use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CategoryGetCategoriesForSelectTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        // Ensure deterministic start
        Category::query()->delete();
    }

    public function test_no_categories_returns_empty(): void
    {
        // Ensure no categories exist
        Category::query()->delete();

        $controller = new CategoryController();
        $result = $controller->getCategoriesForSelect();

        $response = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('No categories found', $response['message']);

        // data should be an empty array
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals([], $response['data']);
    }

    public function test_get_categories_for_select_returns_list(): void
    {
        // Create sample categories
        $cat1 = Category::create(['name' => 'Plants', 'image' => null]);
        $cat2 = Category::create(['name' => 'Seeds', 'image' => null]);

        $controller = new CategoryController();
        $result = $controller->getCategoriesForSelect();

        $response = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Categories retrieved successfully', $response['message']);

        $this->assertArrayHasKey('data', $response);
        $data = $response['data'];

        // Ensure we have an array/list of categories
        $this->assertIsArray($data);
        $this->assertCount(2, $data);

        // Check each item has id and name and contains created names
        $names = array_column($data, 'name');
        $this->assertContains('Plants', $names);
        $this->assertContains('Seeds', $names);

        foreach ($data as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('name', $item);
        }
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
