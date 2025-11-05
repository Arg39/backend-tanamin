<?php

namespace Tests\Unit\Controller\CompanyActivityController;

use App\Http\Controllers\Api\Company\CompanyActivityController;
use App\Models\CompanyActivity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyActivityDestroyCompanyActivityTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test destroyCompanyActivity deletes the record and its image.
     */
    public function test_destroy_company_activity_deletes_record_and_image(): void
    {
        // Fake public storage
        Storage::fake('public');

        // Prepare a fake image file
        $imagePath = 'company_activities/test.jpg';
        Storage::disk('public')->put($imagePath, 'dummy-content');

        // Create a company activity
        $activity = CompanyActivity::create([
            'image' => $imagePath,
            'title' => 'Test Activity',
            'description' => 'Test description',
            'order' => 1,
        ]);

        $controller = new CompanyActivityController();
        $result = $controller->destroyCompanyActivity($activity->id);

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

        // Assert success message
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company activity deleted successfully', $responseData['message']);

        // Assert DB record deleted
        $this->assertNull(CompanyActivity::find($activity->id));

        // Assert file deleted from storage
        Storage::disk('public')->assertMissing($imagePath);
    }

    /**
     * Test destroyCompanyActivity returns not found for missing id.
     */
    public function test_destroy_company_activity_returns_not_found_for_missing_id(): void
    {
        // Ensure id does not exist
        $nonExistentId = 999999;

        $this->assertNull(CompanyActivity::find($nonExistentId));

        $controller = new CompanyActivityController();
        $result = $controller->destroyCompanyActivity($nonExistentId);

        // Normalize response
        $responseData = null;
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            // Controller returns a resource without explicit status for not-found; still assert response exists
            $this->assertEquals(200, $httpResponse->getStatusCode());
            $responseData = $httpResponse->getData(true);
        } elseif (is_array($result)) {
            $responseData = $result;
        } else {
            $encoded = json_encode($result);
            $responseData = json_decode($encoded, true) ?? [];
        }

        // Assert not found message
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company activity not found', $responseData['message']);
    }
}
