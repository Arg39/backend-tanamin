<?php

namespace Tests\Unit\Controller\CompanyActivityController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\Company\CompanyActivityController;
use App\Models\CompanyActivity;

class CompanyActivityUpdateCompanyActivityTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test updateCompanyActivity replaces the image file and updates fields.
     */
    public function test_update_company_activity_replaces_image_and_updates_fields(): void
    {
        Storage::fake('public');

        // Create and store initial image
        $oldFile = UploadedFile::fake()->image('old.jpg', 600, 600)->size(500);
        $oldPath = $oldFile->store('company_activities', 'public');

        // Create initial model
        $activity = CompanyActivity::create([
            'image' => $oldPath,
            'title' => 'Old Title',
            'description' => 'Old description',
            'order' => 1,
        ]);

        // Prepare new image and request
        $newFile = UploadedFile::fake()->image('new.jpg', 600, 600)->size(500);
        $request = Request::create('/', 'POST', [
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ], [], ['image' => $newFile]);

        $controller = new CompanyActivityController();
        $result = $controller->updateCompanyActivity($request, $activity->id);

        // Normalize response
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

        // Assert message indicates update
        $this->assertArrayHasKey('message', $responseData);
        $this->assertStringContainsStringIgnoringCase('updated', $responseData['message']);

        // Assert DB updated
        $this->assertDatabaseHas('company_activities', [
            'id' => $activity->id,
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ]);

        // Refresh model and assert image replaced
        $activity->refresh();
        $this->assertNotEmpty($activity->image);
        $this->assertNotEquals($oldPath, $activity->image);

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($activity->image);
    }

    /**
     * Test updateCompanyActivity updates fields without deleting existing image when no new file provided.
     */
    public function test_update_company_activity_updates_fields_without_replacing_image(): void
    {
        Storage::fake('public');

        // Create and store initial image
        $oldFile = UploadedFile::fake()->image('existing.jpg', 600, 600)->size(500);
        $oldPath = $oldFile->store('company_activities', 'public');

        // Create initial model
        $activity = CompanyActivity::create([
            'image' => $oldPath,
            'title' => 'Initial Title',
            'description' => 'Initial description',
            'order' => 1,
        ]);

        // Prepare request without image
        $request = Request::create('/', 'POST', [
            'title' => 'Title Without Image Change',
            'description' => 'Description Without Image Change',
        ]);

        $controller = new CompanyActivityController();
        $result = $controller->updateCompanyActivity($request, $activity->id);

        // Normalize response
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

        // Assert message indicates update
        $this->assertArrayHasKey('message', $responseData);
        $this->assertStringContainsStringIgnoringCase('updated', $responseData['message']);

        // Assert DB updated and image preserved
        $this->assertDatabaseHas('company_activities', [
            'id' => $activity->id,
            'title' => 'Title Without Image Change',
            'description' => 'Description Without Image Change',
            'image' => $oldPath,
        ]);

        Storage::disk('public')->assertExists($oldPath);
    }
}
