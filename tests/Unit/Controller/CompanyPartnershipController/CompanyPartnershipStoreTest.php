<?php

namespace Tests\Unit\Controller\CompanyPartnershipController;

use App\Http\Controllers\Api\Company\CompanyPartnershipController;
use App\Models\CompanyPartnership;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyPartnershipStoreTest extends TestCase
{
    use DatabaseTransactions;

    public function test_store_company_partnership_success_creates_record_and_stores_logo(): void
    {
        // Arrange
        Storage::fake('public');

        $partnerName = 'Test Co';
        $websiteUrl = 'https://test.example';
        $file = UploadedFile::fake()->image('logo.jpg');

        $request = Request::create('/dummy', 'POST', [
            'partner_name' => $partnerName,
            'website_url' => $websiteUrl,
        ]);
        // attach uploaded file to the request
        $request->files->set('logo', $file);

        $controller = new CompanyPartnershipController();

        // Act
        $result = $controller->storeCompanyPartnership($request);

        // Normalize response
        $responseData = $this->normalizeControllerResponse($result);

        // Assert response message
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company partnership created successfully', $responseData['message']);

        // Assert DB record created
        $partnership = CompanyPartnership::where('partner_name', $partnerName)->first();
        $this->assertNotNull($partnership, 'Expected company partnership record to exist after creation');

        // Assert logo path is set and file stored in public disk
        $this->assertNotEmpty($partnership->logo, 'Expected partnership->logo to be set');
        Storage::disk('public')->assertExists($partnership->logo);
    }

    public function test_store_company_partnership_validation_failure_missing_logo_returns_error(): void
    {
        // Arrange: do not provide logo
        $partnerName = 'NoLogo Co';

        $request = Request::create('/dummy', 'POST', [
            'partner_name' => $partnerName,
            // 'logo' omitted intentionally
        ]);

        $controller = new CompanyPartnershipController();

        // Act
        $result = $controller->storeCompanyPartnership($request);

        // Normalize response
        $responseData = $this->normalizeControllerResponse($result);

        // The controller catches ValidationException and returns PostResource with message from exception.
        // Assert that the response indicates failure and contains a required/logo message.
        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('failed', $responseData['status']);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertStringContainsStringIgnoringCase('required', $responseData['message']);

        // Assert DB did not create the record
        $partnership = CompanyPartnership::where('partner_name', $partnerName)->first();
        $this->assertNull($partnership, 'Expected no company partnership to be created when validation fails');
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
}
