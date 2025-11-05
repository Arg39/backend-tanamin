<?php

namespace Tests\Unit\Controller\CategoryController;

use App\Http\Controllers\Api\CategoryController;
use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class CategoryGetCategoryByIdTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_category_by_id_returns_category(): void
    {
        // Ensure clean state
        Category::query()->delete();

        // Create a category
        $created = Category::create([
            'name' => 'Test Category',
            'image' => 'test.png',
        ]);

        $controller = new CategoryController();
        $result = $controller->getCategoryById($created->id);

        $responseData = $this->normalizeControllerResponse($result);

        // Basic response shape assertions
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Category retrieved successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);

        $item = $this->extractSingleItemFromData($responseData['data']);

        $this->assertIsArray($item);
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('name', $item);

        $this->assertEquals($created->id, $item['id']);
        $this->assertEquals($created->name, $item['name']);
    }

    public function test_get_category_by_id_returns_not_found(): void
    {
        // Ensure clean state
        Category::query()->delete();

        $nonexistentId = 999999;

        $controller = new CategoryController();
        $result = $controller->getCategoryById($nonexistentId);

        $responseData = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Category not found', $responseData['message']);
    }

    /**
     * Normalize controller/resource response into an associative array.
     */
    private function normalizeControllerResponse($result): array
    {
        // If resource-like object (has toResponse), render and return data assoc
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            $this->assertEquals(200, $httpResponse->getStatusCode());
            return $httpResponse->getData(true);
        } elseif (is_array($result)) {
            return $result;
        } elseif (is_object($result)) {
            $encoded = json_encode($result);
            return json_decode($encoded, true) ?? [];
        } else {
            return [];
        }
    }

    /**
     * Extract single item from various possible 'data' shapes.
     * Returns associative array for the item, or null if no item present.
     */
    private function extractSingleItemFromData($data): ?array
    {
        if (is_null($data)) {
            return null;
        }

        // If paginator/resource style: data may be ['data' => [...]] or similar
        if (is_array($data) && isset($data['data']) && (is_array($data['data']) || is_object($data['data']))) {
            $inner = $data['data'];
            // If paginator 'data' is an array of items, pick first
            if (is_array($inner) && array_values($inner) === $inner && count($inner) > 0) {
                $first = $inner[0];
                return $this->normalizeToArray($first);
            }
            // If single item
            return $this->normalizeToArray($inner);
        }

        // If direct object or associative array representing the item
        if (is_object($data) || (is_array($data) && array_values($data) !== $data)) {
            return $this->normalizeToArray($data);
        }

        // If sequential array but single item present, take first
        if (is_array($data) && array_values($data) === $data && count($data) > 0) {
            return $this->normalizeToArray($data[0]);
        }

        return null;
    }

    private function normalizeToArray($val): ?array
    {
        if (is_null($val)) {
            return null;
        }
        if (is_array($val)) {
            return $val;
        }
        if (is_object($val)) {
            return json_decode(json_encode($val), true);
        }
        return null;
    }
}
