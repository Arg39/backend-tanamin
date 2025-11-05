<?php

namespace Tests\Unit\Controller\CompanyActivityController;

use App\Http\Controllers\Api\Company\CompanyActivityController;
use App\Models\CompanyActivity;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class CompanyActivityIndexCompanyActivityTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test indexCompanyActivity returns images for guest (no auth).
     */
    public function test_index_company_activity_returns_images_for_guest(): void
    {
        // Create sample activities
        CompanyActivity::create([
            'image' => 'company_activities/img1.jpg',
            'title' => 'Activity One',
            'description' => 'Description one',
            'order' => 1,
        ]);

        CompanyActivity::create([
            'image' => 'company_activities/img2.jpg',
            'title' => 'Activity Two',
            'description' => 'Description two',
            'order' => 2,
        ]);

        $controller = new CompanyActivityController();
        $request = Request::create('/api/company-activities', 'GET');

        $result = $controller->indexCompanyActivity($request);

        // Normalize response: handle Resource objects, arrays, or other values
        $responseData = null;
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            // Some resources may return 200 or default, assert 200
            $this->assertEquals(200, $httpResponse->getStatusCode());
            $responseData = $httpResponse->getData(true);
        } elseif (is_array($result)) {
            $responseData = $result;
        } else {
            $encoded = json_encode($result);
            $responseData = json_decode($encoded, true) ?? [];
        }

        // Assert message and structure
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company activity images retrieved successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);
        $this->assertNotEmpty($responseData['data']);

        // Each item should have src and alt
        $first = $responseData['data'][0];
        $this->assertArrayHasKey('src', $first);
        $this->assertArrayHasKey('alt', $first);
    }

    /**
     * Test indexCompanyActivity returns paginated table for admin.
     */
    public function test_index_company_activity_returns_table_for_admin(): void
    {
        // Mock JWTAuth to simulate an authenticated admin user
        $adminUser = (object) ['role' => 'admin'];
        JWTAuth::shouldReceive('parseToken->authenticate')->andReturn($adminUser);

        // Create sample activities
        CompanyActivity::create([
            'image' => 'company_activities/imgA.jpg',
            'title' => 'Admin Activity A',
            'description' => 'Desc A',
            'order' => 1,
        ]);

        CompanyActivity::create([
            'image' => 'company_activities/imgB.jpg',
            'title' => 'Admin Activity B',
            'description' => 'Desc B',
            'order' => 2,
        ]);

        $controller = new CompanyActivityController();
        $request = Request::create('/api/company-activities', 'GET');

        $result = $controller->indexCompanyActivity($request);

        // Normalize response
        $responseData = null;
        $status = 200;
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            $status = $httpResponse->getStatusCode();
            $responseData = $httpResponse->getData(true);
        } elseif (is_array($result)) {
            $responseData = $result;
        } else {
            $encoded = json_encode($result);
            $responseData = json_decode($encoded, true) ?? [];
        }

        $this->assertEquals(200, $status);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company activities retrieved successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);

        // The controller wraps the resource collection inside ['data' => $resourceCollection]
        // Depending on resource resolution there may be another 'data' level; handle both.
        if (isset($responseData['data']['data']) && is_array($responseData['data']['data'])) {
            $activitiesArray = $responseData['data']['data'];
        } elseif (isset($responseData['data'][0])) {
            // direct array of items
            $activitiesArray = $responseData['data'];
        } else {
            // fallback: try to extract any nested arrays
            $activitiesArray = [];
            foreach ($responseData['data'] as $v) {
                if (is_array($v)) {
                    $activitiesArray = $v;
                    break;
                }
            }
        }

        // Ensure at least the created activities are present
        $this->assertNotEmpty($activitiesArray);
        // Check that one activity contains the title we created
        $titles = array_column($activitiesArray, 'title');
        $this->assertContains('Admin Activity A', $titles);
        $this->assertContains('Admin Activity B', $titles);
    }
}
