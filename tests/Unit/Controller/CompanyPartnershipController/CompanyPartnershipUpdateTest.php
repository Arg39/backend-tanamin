<?php

namespace Tests\Unit\Controller\CompanyPartnershipController;

use App\Http\Controllers\Api\Company\CompanyPartnershipController;
use App\Models\CompanyPartnership;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyPartnershipUpdateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_update_without_logo_updates_fields(): void
    {
        // Ensure clean state
        CompanyPartnership::query()->delete();

        // Create initial partnership
        $partnership = CompanyPartnership::create([
            'partner_name' => 'Original Name',
            'logo' => 'company_partnerships/original.png',
            'website_url' => 'https://original.example',
        ]);

        $newName = 'Updated Name';
        $newWebsite = 'https://updated.example';

        // Build request without logo file
        $request = new Request([
            'partner_name' => $newName,
            'website_url' => $newWebsite,
        ]);

        $controller = new CompanyPartnershipController();
        $result = $controller->updateCompanyPartnership($request, $partnership->id);

        $responseData = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company partnership updated successfully', $responseData['message']);

        // Assert database updated
        $this->assertDatabaseHas('company_partnerships', [
            'id' => $partnership->id,
            'partner_name' => $newName,
            'website_url' => $newWebsite,
        ]);
    }

    public function test_update_with_logo_replaces_old_and_updates_logo_path(): void
    {
        // Fake storage
        Storage::fake('public');

        // Put an old file so the controller sees it exists and will delete it
        $oldPath = 'company_partnerships/old.png';
        Storage::disk('public')->put($oldPath, 'old content');

        $partnership = CompanyPartnership::create([
            'partner_name' => 'Has Old Logo',
            'logo' => $oldPath,
            'website_url' => 'https://oldlogo.example',
        ]);

        // Create fake uploaded file
        $uploadedFile = UploadedFile::fake()->image('newlogo.png');

        $newName = 'Has New Logo';
        $newWebsite = 'https://newlogo.example';

        // Build request and attach uploaded file
        $request = new Request([
            'partner_name' => $newName,
            'website_url' => $newWebsite,
        ]);
        $request->files->set('logo', $uploadedFile);

        $controller = new CompanyPartnershipController();
        $result = $controller->updateCompanyPartnership($request, $partnership->id);

        $responseData = $this->normalizeControllerResponse($result);

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company partnership updated successfully', $responseData['message']);

        // Fetch fresh model
        $fresh = $partnership->fresh();

        // Logo path should be changed
        $this->assertNotEquals($oldPath, $fresh->logo);

        // Old file should be deleted
        $this->assertFalse(Storage::disk('public')->exists($oldPath), 'Old logo should be deleted from storage');

        // New file should exist in storage
        $this->assertTrue(Storage::disk('public')->exists($fresh->logo), 'New logo should be stored in public disk');

        // Database updated for name and website
        $this->assertDatabaseHas('company_partnerships', [
            'id' => $partnership->id,
            'partner_name' => $newName,
            'website_url' => $newWebsite,
            'logo' => $fresh->logo,
        ]);
    }

    public function test_update_nonexistent_returns_not_found(): void
    {
        // Ensure no record with this id
        CompanyPartnership::query()->delete();

        $request = new Request([
            'partner_name' => 'Does Not Matter',
            'website_url' => 'https://none.example',
        ]);

        $controller = new CompanyPartnershipController();
        $result = $controller->updateCompanyPartnership($request, 999999);

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
