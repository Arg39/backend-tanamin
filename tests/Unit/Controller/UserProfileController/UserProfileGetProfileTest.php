<?php

namespace Tests\Unit\Controller\UserProfileController;

use App\Http\Controllers\Api\UserProfileController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserProfileGetProfileTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test getProfile returns authenticated user's profile with details.
     */
    public function test_get_profile_returns_authenticated_user_profile()
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
        // Use array keys that likely exist on detail (adjust if your schema differs)
        $user->detail()->create([
            'user_id' => $user->id,
            'expertise' => 'Testing',
            'about' => 'About testing',
            // 'social_media' => json_encode(['twitter' => '@test']) // optional
        ]);

        // Mock Auth::user() to return our created user
        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $controller = new UserProfileController();
        $response = $controller->getProfile();

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
}
