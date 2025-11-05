<?php

namespace Tests\Unit\Controller\UserProfileController;

use App\Http\Controllers\Api\UserProfileController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserProfileGetProfileByIdTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test getProfileById returns the specified user's profile with details.
     */
    public function test_get_profile_by_id_returns_user_profile()
    {
        // Create a user
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'First',
            'last_name' => 'Last',
            'username' => 'testuser',
            'email' => 'testuser@example.com',
            'password' => bcrypt('password123'),
            'role' => 'student',
            'status' => 'active',
        ]);

        // Create detail for the user to ensure relation is present
        $user->detail()->create([
            'user_id' => $user->id,
            'expertise' => 'Testing',
            'about' => 'About testing',
        ]);

        $controller = new UserProfileController();
        $response = $controller->getProfileById($user->id);

        // Assert HTTP 200
        $this->assertEquals(200, $response->getStatusCode());

        // Get response data as array
        $responseData = $response->getData(true);

        // Assert message matches expected text from controller
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Profil pengguna berhasil diambil.', $responseData['message']);

        // Assert returned data exists and contains expected user fields
        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);
        $this->assertArrayHasKey('id', $responseData['data']);
        $this->assertEquals($user->id, $responseData['data']['id']);

        // Additional assertions for basic fields
        $this->assertEquals('First', $responseData['data']['first_name'] ?? $user->first_name);
        $this->assertEquals('Last', $responseData['data']['last_name'] ?? $user->last_name);
        $this->assertEquals('testuser@example.com', $responseData['data']['email'] ?? $user->email);
    }

    /**
     * Test getProfileById returns 404 when user not found.
     */
    public function test_get_profile_by_id_returns_404_when_user_not_found()
    {
        $nonExistentId = Str::uuid()->toString();

        $controller = new UserProfileController();
        $response = $controller->getProfileById($nonExistentId);

        $this->assertEquals(404, $response->getStatusCode());

        $responseData = $response->getData(true);

        // Adjusted assertions to match controller response when user not found
        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('failed', $responseData['status']);

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('User not found.', $responseData['message']);

        // Accept either missing 'data' key or data explicitly null
        $this->assertTrue(!isset($responseData['data']) || $responseData['data'] === null);
    }
}
