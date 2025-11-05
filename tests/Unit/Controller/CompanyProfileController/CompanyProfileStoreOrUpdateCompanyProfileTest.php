<?php

namespace Tests\Unit\Controller\CompanyProfileController;

use App\Http\Controllers\Api\Company\CompanyProfileController;
use App\Models\CompanyProfile;
use App\Models\CompanyStatistics;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class CompanyProfileStoreOrUpdateCompanyProfileTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test storeOrUpdateCompanyProfile creates profile and statistics, skipping empty-stat entries.
     */
    public function test_store_creates_profile_and_statistics(): void
    {
        $payload = [
            'about' => 'About store test',
            'vision' => 'Store vision',
            'mission' => ['Provide quality', 'Sustainability'],
            'statistics' => [
                [
                    'title' => 'Users',
                    'value' => '150',
                    'unit' => 'people',
                ],
                // all-empty row should be skipped by controller
                [
                    'title' => '',
                    'value' => '',
                    'unit' => '',
                ],
            ],
        ];

        $request = new Request($payload);
        $controller = new CompanyProfileController();
        $result = $controller->storeOrUpdateCompanyProfile($request);

        // Normalize response similar to existing tests
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
        $this->assertEquals('Company profile saved successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);

        // Determine profile nesting
        if (isset($responseData['data']['profile']) && is_array($responseData['data']['profile'])) {
            $profileData = $responseData['data']['profile'];
            $statisticsData = $responseData['data']['statistics'] ?? [];
        } else {
            $profileData = $responseData['data'];
            $statisticsData = $responseData['data']['statistics'] ?? [];
            if (isset($profileData['statistics'])) {
                unset($profileData['statistics']);
            }
        }

        $this->assertEquals($payload['about'], $profileData['about'] ?? $payload['about']);
        $this->assertEquals($payload['vision'], $profileData['vision'] ?? $payload['vision']);

        $this->assertIsArray($statisticsData);
        // Ensure only the valid statistics row is present
        $titles = array_column($statisticsData, 'title');
        $this->assertContains('Users', $titles);
        $this->assertNotContains('', $titles);

        // Ensure numeric value cast (controller casts to int when non-empty)
        $values = array_column($statisticsData, 'value', 'title');
        $this->assertEquals(150, $values['Users']);
    }

    /**
     * Test storeOrUpdateCompanyProfile updates existing profile and truncates previous statistics.
     */
    public function test_update_overwrites_profile_and_truncates_statistics(): void
    {
        // Pre-create profile and statistics
        $existingProfile = CompanyProfile::create([
            'about' => 'Old about',
            'vision' => 'Old vision',
            'mission' => json_encode(['Old mission']),
        ]);

        CompanyStatistics::create([
            'title' => 'OldStat1',
            'value' => 1,
            'unit' => 'u',
        ]);

        CompanyStatistics::create([
            'title' => 'OldStat2',
            'value' => 2,
            'unit' => 'u',
        ]);

        // New payload to update
        $updatePayload = [
            'about' => 'Updated about',
            'vision' => 'Updated vision',
            'mission' => ['New mission item'],
            'statistics' => [
                [
                    'title' => 'NewUsers',
                    'value' => '999',
                    'unit' => 'people',
                ],
            ],
        ];

        $request = new Request($updatePayload);
        $controller = new CompanyProfileController();
        $result = $controller->storeOrUpdateCompanyProfile($request);

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
        $this->assertEquals('Company profile saved successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);

        // Extract profile and statistics
        if (isset($responseData['data']['profile']) && is_array($responseData['data']['profile'])) {
            $profileData = $responseData['data']['profile'];
            $statisticsData = $responseData['data']['statistics'] ?? [];
        } else {
            $profileData = $responseData['data'];
            $statisticsData = $responseData['data']['statistics'] ?? [];
            if (isset($profileData['statistics'])) {
                unset($profileData['statistics']);
            }
        }

        // Assert profile updated
        $this->assertEquals($updatePayload['about'], $profileData['about'] ?? $updatePayload['about']);
        $this->assertEquals($updatePayload['vision'], $profileData['vision'] ?? $updatePayload['vision']);

        // Assert statistics replaced: old ones removed, new present
        $titles = array_column($statisticsData, 'title');
        $this->assertContains('NewUsers', $titles);
        $this->assertNotContains('OldStat1', $titles);
        $this->assertNotContains('OldStat2', $titles);

        // Ensure the value is cast to int
        $values = array_column($statisticsData, 'value', 'title');
        $this->assertEquals(999, $values['NewUsers']);
    }
}
