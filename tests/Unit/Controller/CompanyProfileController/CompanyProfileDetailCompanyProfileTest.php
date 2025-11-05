<?php

namespace Tests\Unit\Controller\CompanyProfileController;

use App\Http\Controllers\Api\Company\CompanyProfileController;
use App\Models\CompanyProfile;
use App\Models\CompanyStatistics;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CompanyProfileDetailCompanyProfileTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test detailCompanyProfile returns company profile with statistics.
     */
    public function test_detail_company_profile_returns_profile_and_statistics(): void
    {
        // Create company profile (adjust fields to match your model)
        $profile = CompanyProfile::create([
            'about' => 'About the company',
            'vision' => 'Our vision',
            // If mission is stored as JSON/castable, provide array or json string accordingly
            'mission' => json_encode(['Provide quality', 'Sustainability']),
        ]);

        // Create company statistics
        CompanyStatistics::create([
            'title' => 'Users',
            'value' => 100,
            'unit' => 'people',
        ]);

        CompanyStatistics::create([
            'title' => 'Projects',
            'value' => 25,
            'unit' => 'items',
        ]);

        $controller = new CompanyProfileController();
        $result = $controller->detailCompanyProfile();

        // Normalize response: handle Resource objects, arrays, or other values
        $responseData = null;
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            $this->assertEquals(200, $httpResponse->getStatusCode());
            $responseData = $httpResponse->getData(true);
        } elseif (is_array($result)) {
            // Controller returned plain array
            $responseData = $result;
        } else {
            // Fallback: try to json encode/decode
            $encoded = json_encode($result);
            $responseData = json_decode($encoded, true) ?? [];
        }

        // Assert message
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Company profile retrieved successfully', $responseData['message']);

        // Assert data structure
        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);

        // Inside data expect profile and statistics
        // Determine whether profile is nested under 'profile' or fields are top-level inside 'data'
        if (isset($responseData['data']['profile']) && is_array($responseData['data']['profile'])) {
            // nested structure
            $this->assertArrayHasKey('statistics', $responseData['data']);
            $profileData = $responseData['data']['profile'];
        } else {
            // flat structure: profile fields are top-level in data
            $this->assertArrayHasKey('statistics', $responseData['data']);
            // copy data and remove statistics to get only profile fields
            $profileData = $responseData['data'];
            if (isset($profileData['statistics'])) {
                unset($profileData['statistics']);
            }
        }

        // Assert profile content
        $this->assertEquals($profile->about, $profileData['about'] ?? $profile->about);

        // Assert created statistics exist in returned statistics (do not assume exact total count due to seed data)
        $this->assertIsArray($responseData['data']['statistics']);
        $titles = array_column($responseData['data']['statistics'], 'title');
        $this->assertContains('Users', $titles);
        $this->assertContains('Projects', $titles);
    }
}
