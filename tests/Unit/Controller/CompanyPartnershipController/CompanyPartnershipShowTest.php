<?php

namespace Tests\Unit\Controller\CompanyPartnershipController;

use App\Http\Controllers\Api\Company\CompanyPartnershipController;
use App\Models\CompanyPartnership;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Illuminate\Http\Request;

class CompanyPartnershipShowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_show_returns_partnership_detail(): void
    {
        // Ensure clean state
        CompanyPartnership::query()->delete();

        $partnershipData = [
            'partner_name' => 'ShowTest Co',
            'logo' => 'showtest.png',
            'website_url' => 'https://showtest.example',
        ];

        $partnership = CompanyPartnership::create($partnershipData);

        $controller = new CompanyPartnershipController();
        $result = $controller->showCompanyPartnership($partnership->id);

        $responseData = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company partnership detail retrieved successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $item = $responseData['data'];

        if (is_object($item)) {
            $item = json_decode(json_encode($item), true);
        }

        $this->assertTrue(isset($item['partner_name']) || isset($item['name']), 'Expected data to contain partner_name or name');

        $returnedName = $item['partner_name'] ?? $item['name'] ?? null;
        $this->assertEquals($partnershipData['partner_name'], $returnedName);
    }

    public function test_show_not_found_returns_error(): void
    {
        // Ensure clean state
        CompanyPartnership::query()->delete();

        $controller = new CompanyPartnershipController();
        $result = $controller->showCompanyPartnership(999999);

        $responseData = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company partnership not found', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertNull($responseData['data']);
    }

    /**
     * Normalize controller response into an associative array.
     */
    private function normalizeControllerResponse($result): array
    {
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            $this->assertEquals(200, $httpResponse->getStatusCode());
            $data = $httpResponse->getData(true);
            if (!array_key_exists('data', $data)) {
                $data['data'] = null;
            }
            return $data;
        } elseif (is_array($result)) {
            if (!array_key_exists('data', $result)) {
                $result['data'] = null;
            }
            return $result;
        } else {
            $encoded = json_encode($result);
            $decoded = json_decode($encoded, true) ?? [];
            if (!array_key_exists('data', $decoded)) {
                $decoded['data'] = null;
            }
            return $decoded;
        }
    }
}
