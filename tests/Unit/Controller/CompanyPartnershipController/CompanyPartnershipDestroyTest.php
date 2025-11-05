<?php

namespace Tests\Unit\Controller\CompanyPartnershipController;

use App\Http\Controllers\Api\Company\CompanyPartnershipController;
use App\Models\CompanyPartnership;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyPartnershipDestroyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_destroy_existing_deletes_record_and_logo(): void
    {
        // Fake public storage so no real FS writes occur
        Storage::fake('public');

        // Prepare a logo file path and ensure it exists in fake storage
        $logoPath = 'company_partnerships/testlogo.png';
        Storage::disk('public')->put($logoPath, 'fake content');

        // Create partnership referencing the logo
        $partnership = CompanyPartnership::create([
            'partner_name' => 'To Be Deleted',
            'logo' => $logoPath,
            'website_url' => 'https://delete.example',
        ]);

        $controller = new CompanyPartnershipController();
        $result = $controller->destroyCompanyPartnership($partnership->id);

        $responseData = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company partnership deleted successfully', $responseData['message']);

        // Assert database record removed
        $this->assertDatabaseMissing('company_partnerships', [
            'id' => $partnership->id,
        ]);

        // Assert logo file removed from storage
        $this->assertFalse(Storage::disk('public')->exists($logoPath), 'Logo file should be deleted from storage');
    }

    public function test_destroy_nonexistent_returns_not_found(): void
    {
        // Ensure no record with this id
        CompanyPartnership::query()->delete();

        $controller = new CompanyPartnershipController();
        $result = $controller->destroyCompanyPartnership(999999);

        $responseData = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company partnership not found', $responseData['message']);
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
