<?php

namespace Tests\Unit\Controller\CompanyActivityController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\Company\CompanyActivityController;
use App\Models\CompanyActivity;

class CompanyActivityStoreCompanyActivityTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test storeCompanyActivity creates a CompanyActivity record and stores the uploaded image.
     */
    public function test_store_company_activity_creates_record_and_stores_image(): void
    {
        // Fake the public disk so no real files are written
        Storage::fake('public');

        // Create a fake image file
        $file = UploadedFile::fake()->image('activity.jpg', 600, 600)->size(500);

        // Build a request containing title, description and the fake image file
        $request = Request::create('/', 'POST', [
            'title' => 'Test Activity',
            'description' => 'Test description',
        ], [], ['image' => $file]);

        // Instantiate controller and call the store method
        $controller = new CompanyActivityController();
        $result = $controller->storeCompanyActivity($request);

        // Normalize response to array for assertions
        $responseData = null;
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse($request);
            $this->assertEquals(200, $httpResponse->getStatusCode());
            $responseData = $httpResponse->getData(true);
        } elseif (is_array($result)) {
            $responseData = $result;
        } else {
            $responseData = json_decode(json_encode($result), true) ?? [];
        }

        // Assert response message indicates success/creation
        $this->assertArrayHasKey('message', $responseData);
        $this->assertStringContainsStringIgnoringCase('created', $responseData['message']);

        // Assert database has the created company activity
        $this->assertDatabaseHas('company_activities', [
            'title' => 'Test Activity',
            'description' => 'Test description',
        ]);

        // Fetch the created activity and assert image saved and exists in fake storage
        $activity = CompanyActivity::where('title', 'Test Activity')->first();
        $this->assertNotNull($activity, 'Expected CompanyActivity record was not created.');
        $this->assertNotEmpty($activity->image, 'Expected image path to be set on the model.');

        Storage::disk('public')->assertExists($activity->image);
    }
}
