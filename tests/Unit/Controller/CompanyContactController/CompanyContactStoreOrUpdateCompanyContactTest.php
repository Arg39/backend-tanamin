<?php

namespace Tests\Unit\Controller\CompanyContactController;

use App\Http\Controllers\Api\Company\CompanyContactController;
use App\Models\CompanyContact;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class CompanyContactStoreOrUpdateCompanyContactTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test storeOrUpdateCompanyContact creates a new contact when none exists.
     */
    public function test_store_creates_contact_when_none_exists(): void
    {
        // Ensure no CompanyContact exists
        CompanyContact::query()->delete();

        $payload = [
            'telephone' => '0811223344',
            'email' => 'hello@example.com',
            'address' => 'Jl. Test 1',
            'social_media' => [
                'facebook' => 'fb.com/test',
                'instagram' => 'instagram.com/test'
            ],
        ];

        $request = Request::create('/api/company/contact', 'POST', $payload);

        $controller = new CompanyContactController();
        $result = $controller->storeOrUpdateCompanyContact($request);

        // Normalize response
        $responseData = null;
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            $status = $httpResponse->getStatusCode();
            $this->assertTrue(in_array($status, [200, 201]), "Unexpected status code: {$status}");
            $responseData = $httpResponse->getData(true);
        } elseif (is_array($result)) {
            $responseData = $result;
        } else {
            $encoded = json_encode($result);
            $responseData = json_decode($encoded, true) ?? [];
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Contact updated successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);

        $data = $responseData['data'];
        // Normalize if resource nested
        if (isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        $this->assertEquals($payload['telephone'], $data['telephone'] ?? null);
        $this->assertEquals($payload['email'], $data['email'] ?? null);
        $this->assertEquals($payload['address'], $data['address'] ?? null);

        // Normalize social_media for comparison
        $returnedSocial = $data['social_media'];
        if (is_string($returnedSocial)) {
            $decoded = json_decode($returnedSocial, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $returnedSocial = $decoded;
            }
        }

        $this->assertEquals($payload['social_media'], $returnedSocial);

        // Assert persisted in DB
        $contact = CompanyContact::first();
        $this->assertNotNull($contact);
        $this->assertEquals($payload['telephone'], $contact->telephone);
        $this->assertEquals($payload['email'], $contact->email);
        $this->assertEquals($payload['address'], $contact->address);
        $storedSocial = is_string($contact->social_media) ? json_decode($contact->social_media, true) : $contact->social_media;
        $this->assertEquals($payload['social_media'], $storedSocial);
    }

    /**
     * Test storeOrUpdateCompanyContact updates an existing contact.
     */
    public function test_update_updates_existing_contact(): void
    {
        // Ensure clean state
        CompanyContact::query()->delete();

        $initial = CompanyContact::create([
            'telephone' => '0800000000',
            'email' => 'init@example.com',
            'address' => 'Initial Address',
            'social_media' => json_encode(['twitter' => 'twitter.com/init']),
        ]);

        $payload = [
            'telephone' => '0899999999',
            'email' => 'updated@example.com',
            'address' => 'Updated Address',
            'social_media' => [
                'twitter' => 'twitter.com/updated',
                'linkedin' => 'linkedin.com/updated'
            ],
        ];

        $request = Request::create('/api/company/contact', 'POST', $payload);

        $controller = new CompanyContactController();
        $result = $controller->storeOrUpdateCompanyContact($request);

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

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Contact updated successfully', $responseData['message']);

        // Assert DB updated
        $contact = CompanyContact::first();
        $this->assertEquals($payload['telephone'], $contact->telephone);
        $this->assertEquals($payload['email'], $contact->email);
        $this->assertEquals($payload['address'], $contact->address);
        $storedSocial = is_string($contact->social_media) ? json_decode($contact->social_media, true) : $contact->social_media;
        $this->assertEquals($payload['social_media'], $storedSocial);
    }

    /**
     * Test empty strings are converted to null for telephone/email/address and social media empty entries become null.
     */
    public function test_empty_strings_converted_to_null(): void
    {
        CompanyContact::query()->delete();

        $payload = [
            'telephone' => '',
            'email' => '',
            'address' => '',
            'social_media' => [
                'facebook' => '',
                'instagram' => 'insta.com/ok'
            ],
        ];

        $request = Request::create('/api/company/contact', 'POST', $payload);

        $controller = new CompanyContactController();
        $result = $controller->storeOrUpdateCompanyContact($request);

        // Normalize response (ensure 200 or 201)
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            $status = $httpResponse->getStatusCode();
            $this->assertTrue(in_array($status, [200, 201]), "Unexpected status code: {$status}");
        }

        $contact = CompanyContact::first();
        $this->assertNotNull($contact);

        $this->assertNull($contact->telephone);
        $this->assertNull($contact->email);
        $this->assertNull($contact->address);

        $storedSocial = is_string($contact->social_media) ? json_decode($contact->social_media, true) : $contact->social_media;
        $this->assertIsArray($storedSocial);
        $this->assertArrayHasKey('facebook', $storedSocial);
        $this->assertArrayHasKey('instagram', $storedSocial);
        $this->assertNull($storedSocial['facebook']);
        $this->assertEquals('insta.com/ok', $storedSocial['instagram']);
    }
}
