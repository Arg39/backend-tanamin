<?php

namespace Tests\Unit\Controller\CompanyPartnershipController;

use App\Http\Controllers\Api\Company\CompanyPartnershipController;
use App\Models\CompanyPartnership;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class CompanyPartnershipIndexTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_company_partnership_unauthenticated_returns_logos_list(): void
    {
        // Ensure no other partnerships interfere
        CompanyPartnership::query()->delete();

        // Create sample partnerships with deterministic partner_name to control ordering
        $partnershipsData = [
            [
                'partner_name' => 'Alpha Co',
                'logo' => 'alpha.png',
                'website_url' => 'https://alpha.example',
            ],
            [
                'partner_name' => 'Beta Ltd',
                'logo' => 'beta.png',
                'website_url' => 'https://beta.example',
            ],
            [
                'partner_name' => 'Gamma Inc',
                'logo' => 'gamma.png',
                'website_url' => 'https://gamma.example',
            ],
        ];

        foreach ($partnershipsData as $data) {
            CompanyPartnership::create($data);
        }

        $request = new Request(); // no token -> unauthenticated path
        $controller = new CompanyPartnershipController();
        $result = $controller->indexCompanyPartnership($request);

        // Normalize response into array
        $responseData = $this->normalizeControllerResponse($result);

        // Assert message
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company partnership logos retrieved successfully', $responseData['message']);

        // Assert data exists and extract items flexibly
        $this->assertArrayHasKey('data', $responseData);
        $tableData = $responseData['data'];

        $items = $this->extractItemsFromTableData($tableData);

        // Expect at least the created partnerships present
        $this->assertGreaterThanOrEqual(count($partnershipsData), count($items));

        // Normalize items and assert required keys
        $normalizedItems = array_map(function ($it) {
            if (is_object($it)) {
                return json_decode(json_encode($it), true);
            }
            return $it;
        }, $items);

        foreach ($normalizedItems as $item) {
            $this->assertArrayHasKey('logo', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('website', $item);
        }

        // Verify created partner names present
        $foundNames = array_map(fn($it) => $it['name'] ?? null, $normalizedItems);
        foreach ($partnershipsData as $expected) {
            $this->assertContains($expected['partner_name'], $foundNames);
        }
    }

    public function test_index_company_partnership_as_admin_returns_paginated_table(): void
    {
        // Ensure clean state
        CompanyPartnership::query()->delete();

        // Create sample partnerships
        $partnershipsData = [
            [
                'partner_name' => 'Delta Co',
                'logo' => 'delta.png',
                'website_url' => 'https://delta.example',
            ],
            [
                'partner_name' => 'Epsilon Ltd',
                'logo' => 'epsilon.png',
                'website_url' => 'https://epsilon.example',
            ],
        ];

        foreach ($partnershipsData as $data) {
            CompanyPartnership::create($data);
        }

        // Mock JWTAuth to return a user-like object with role 'admin'
        $adminUser = (object) ['role' => 'admin'];
        JWTAuth::shouldReceive('parseToken->authenticate')->andReturn($adminUser);

        $perPage = 10;
        $request = new Request(['per_page' => $perPage]);

        $controller = new CompanyPartnershipController();
        $result = $controller->indexCompanyPartnership($request);

        // Normalize response
        $responseData = $this->normalizeControllerResponse($result);

        // Assert message
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company partnerships retrieved successfully', $responseData['message']);

        // Assert data exists and extract items flexibly
        $this->assertArrayHasKey('data', $responseData);
        $tableData = $responseData['data'];
        $items = $this->extractItemsFromTableData($tableData);

        // Normalize and ensure items contain partnership attributes (either partner_name or name)
        $normalizedItems = array_map(function ($it) {
            if (is_object($it)) {
                return json_decode(json_encode($it), true);
            }
            return $it;
        }, $items);

        $this->assertGreaterThanOrEqual(count($partnershipsData), count($normalizedItems));

        foreach ($normalizedItems as $item) {
            $this->assertTrue(
                isset($item['partner_name']) || isset($item['name']),
                'Expected item to have partner_name or name key'
            );
        }

        // Verify expected partner names exist in returned items (order-agnostic)
        $foundNames = array_map(fn($it) => $it['partner_name'] ?? ($it['name'] ?? null), $normalizedItems);
        foreach ($partnershipsData as $expected) {
            $this->assertContains($expected['partner_name'], $foundNames);
        }
    }

    /**
     * Normalize controller response into an associative array.
     */
    private function normalizeControllerResponse($result): array
    {
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            $this->assertEquals(200, $httpResponse->getStatusCode());
            return $httpResponse->getData(true);
        } elseif (is_array($result)) {
            return $result;
        } else {
            $encoded = json_encode($result);
            return json_decode($encoded, true) ?? [];
        }
    }

    /**
     * Flexible extractor for items from a controller's 'data' / table structure.
     */
    private function extractItemsFromTableData($tableData): array
    {
        $items = [];

        // 1) paginator under 'data' key
        if (is_array($tableData) && isset($tableData['data']) && is_array($tableData['data'])) {
            $items = $tableData['data'];
        }
        // 2) resources that use 'items' key
        elseif (is_array($tableData) && isset($tableData['items']) && is_array($tableData['items'])) {
            $items = $tableData['items'];
        }
        // 3) direct sequential array
        elseif (is_array($tableData) && array_values($tableData) === $tableData) {
            $items = $tableData;
        }
        // 4) associative but numeric keys (treat as list)
        elseif (is_array($tableData) && $this->isAssocArrayOfItems($tableData)) {
            $items = array_values($tableData);
        } else {
            $items = [];
        }

        return $items;
    }

    /**
     * Helper to detect associative array that contains items (not metadata).
     */
    private function isAssocArrayOfItems(array $arr): bool
    {
        $keys = array_keys($arr);
        foreach ($keys as $k) {
            if (!is_int($k)) {
                return false;
            }
        }
        return true;
    }
}
