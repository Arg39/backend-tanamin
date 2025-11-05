<?php

namespace Tests\Unit\Controller\CategoryController;

use App\Http\Controllers\Api\CategoryController;
use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryIndexTest extends TestCase
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

    /**
     * Test that index returns categories and response structure is correct.
     */
    public function test_index_returns_categories()
    {
        $cat1 = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Plants',
            'image' => null,
        ]);

        $cat2 = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Animals',
            'image' => null,
        ]);

        $controller = new CategoryController();
        $request = new Request();
        $response = $controller->index($request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Categories retrieved successfully', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);

        $items = [];
        if (isset($responseData['data']['data'])) {
            $items = $responseData['data']['data'];
        } elseif (is_array($responseData['data'])) {
            $items = $responseData['data'];
        }

        $this->assertNotEmpty($items, 'Expected categories list not to be empty');
        $this->assertGreaterThanOrEqual(2, count($items));
    }
}
