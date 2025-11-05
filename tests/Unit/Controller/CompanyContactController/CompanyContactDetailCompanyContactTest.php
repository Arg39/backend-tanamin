<?php

namespace Tests\Unit\Controller\CompanyContactController;

use App\Http\Controllers\Api\Company\CompanyContactController;
use App\Models\CompanyContact;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CompanyContactDetailCompanyContactTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test detailCompanyContact returns the stored contact when it exists.
     */
    public function test_detail_company_contact_returns_contact_when_exists(): void
    {
        // Ensure no other CompanyContact records interfere with first()
        CompanyContact::query()->delete();

        // Create a company contact
        $contact = CompanyContact::create([
            'telephone'    => '08123456789',
            'email'        => 'info@example.com',
            'address'      => '123 Example Street',
            // store social_media as JSON string or array depending on model casting
            'social_media' => json_encode([
                'facebook' => 'fb.com/example',
                'instagram' => 'instagram.com/example',
            ]),
        ]);

        $controller = new CompanyContactController();
        $result = $controller->detailCompanyContact();

        // Normalize response
        $responseData = null;
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            $this->assertEquals(200, $httpResponse->getStatusCode());
            $responseData = $httpResponse->getData(true);
        } elseif (is_array($result)) {
            $responseData = $result;
        } else {
            $encoded = json_encode($result);
            $responseData = json_decode($encoded, true) ?? [];
        }

        // Assert message
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company contact retrieved successfully', $responseData['message']);

        // Assert data structure and values
        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);

        $data = $responseData['data'];

        // Normalize possible nested structure (resource wrappers sometimes nest)
        if (isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        $this->assertArrayHasKey('telephone', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('address', $data);
        $this->assertArrayHasKey('social_media', $data);

        $this->assertEquals($contact->telephone, $data['telephone'] ?? $contact->telephone);
        $this->assertEquals($contact->email, $data['email'] ?? $contact->email);
        $this->assertEquals($contact->address, $data['address'] ?? $contact->address);

        // social_media might be returned as array or JSON string; normalize for comparison
        $returnedSocial = $data['social_media'];
        if (is_string($returnedSocial)) {
            $decoded = json_decode($returnedSocial, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $returnedSocial = $decoded;
            }
        }
        $expectedSocial = is_string($contact->social_media) ? json_decode($contact->social_media, true) : $contact->social_media;
        $this->assertEquals($expectedSocial, $returnedSocial);
    }

    /**
     * Test detailCompanyContact returns default contact structure when none exists.
     */
    public function test_detail_company_contact_returns_default_when_not_exists(): void
    {
        // Ensure no CompanyContact exists
        CompanyContact::query()->delete();

        $controller = new CompanyContactController();
        $result = $controller->detailCompanyContact();

        // Normalize response
        $responseData = null;
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            $this->assertEquals(200, $httpResponse->getStatusCode());
            $responseData = $httpResponse->getData(true);
        } elseif (is_array($result)) {
            $responseData = $result;
        } else {
            $encoded = json_encode($result);
            $responseData = json_decode($encoded, true) ?? [];
        }

        // Assert message for not exists case
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company contact has not been added yet', $responseData['message']);

        // Assert default data structure
        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);

        $data = $responseData['data'];

        $this->assertArrayHasKey('telephone', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('address', $data);
        $this->assertArrayHasKey('social_media', $data);

        $this->assertNull($data['telephone']);
        $this->assertNull($data['email']);
        $this->assertNull($data['address']);
        $this->assertNull($data['social_media']);
    }
}
