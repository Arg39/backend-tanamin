<?php

namespace Tests\Unit\Controller\CompanyContactUsController;

use App\Http\Controllers\Api\Company\CompanyContactUsController;
use App\Models\ContactUsMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class CompanyContactUsStoreTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test that store() saves a contact us message and returns the created resource.
     */
    public function test_store_saves_and_returns_created_message(): void
    {
        // Ensure no other messages interfere
        ContactUsMessage::query()->delete();

        // Prepare valid payload
        $payload = [
            'name' => 'Test User',
            'email' => 'test.user@example.com',
            'subject' => 'Test Subject',
            'message' => 'This is a test message.',
        ];

        // Create request and call controller
        $request = new Request($payload);
        $controller = new CompanyContactUsController();
        $result = $controller->store($request);

        // Normalize resource to HTTP response if possible
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            // Accept either 200 or 201 depending on implementation
            $this->assertContains($httpResponse->getStatusCode(), [200, 201]);
            $responseData = $httpResponse->getData(true);
        } elseif (is_array($result)) {
            $responseData = $result;
        } else {
            $encoded = json_encode($result);
            $responseData = json_decode($encoded, true) ?? [];
        }

        // Assert response structure and success message
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Contact us message stored successfully.', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);

        // Verify record exists in DB
        $created = ContactUsMessage::where('email', $payload['email'])->first();
        $this->assertNotNull($created, 'Expected ContactUsMessage to be created in database');

        $this->assertEquals($payload['name'], $created->name);
        $this->assertEquals($payload['email'], $created->email);
        $this->assertEquals($payload['subject'], $created->subject);
        $this->assertEquals($payload['message'], $created->message);

        // Normalize response data to array for assertions
        $responseItem = $responseData['data'];
        if (is_object($responseItem)) {
            $responseItem = json_decode(json_encode($responseItem), true);
        }

        // Response data should contain the created record's id and fields
        $this->assertArrayHasKey('id', $responseItem);
        $this->assertEquals($created->id, $responseItem['id']);
        $this->assertEquals($created->name, $responseItem['name']);
        $this->assertEquals($created->email, $responseItem['email']);
        $this->assertEquals($created->subject, $responseItem['subject']);
        $this->assertEquals($created->message, $responseItem['message']);
    }
}
