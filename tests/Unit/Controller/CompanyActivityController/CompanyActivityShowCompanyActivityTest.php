<?php

namespace Tests\Unit\Controller\CompanyActivityController;

use App\Http\Controllers\Api\Company\CompanyActivityController;
use App\Models\CompanyActivity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CompanyActivityShowCompanyActivityTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test showCompanyActivity returns the expected activity data.
     */
    public function test_show_company_activity_returns_activity(): void
    {
        // Create a company activity record
        $activity = CompanyActivity::create([
            'image' => 'company_activities/example.jpg',
            'title' => 'Test Activity Title',
            'description' => 'Test activity description.',
            'order' => 1,
        ]);

        $controller = new CompanyActivityController();
        $result = $controller->showCompanyActivity($activity->id);

        // Normalize response
        $responseData = null;
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            $this->assertEquals(200, $httpResponse->getStatusCode());
            // getData(true) returns associative array
            $responseData = $httpResponse->getData(true);
        } elseif (is_array($result)) {
            $responseData = $result;
        } else {
            $encoded = json_encode($result);
            $responseData = json_decode($encoded, true) ?? [];
        }

        // Basic assertions on response shape and content
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company activity retrieved successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);

        // The controller wraps CompanyActivityResource inside PostResource.
        // Assert that returned data contains title and description matching created model.
        $this->assertArrayHasKey('title', $responseData['data']);
        $this->assertEquals($activity->title, $responseData['data']['title']);

        $this->assertArrayHasKey('description', $responseData['data']);
        $this->assertEquals($activity->description, $responseData['data']['description']);

        // Image field may be named 'image' or 'image_url' depending on resource - check at least one.
        $this->assertTrue(
            array_key_exists('image', $responseData['data']) || array_key_exists('image_url', $responseData['data']),
            'Response data should contain image or image_url'
        );
    }
}
