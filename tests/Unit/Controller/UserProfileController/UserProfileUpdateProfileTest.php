<?php

namespace Tests\Unit\Controller\UserProfileController;

use App\Http\Controllers\Api\UserProfileController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserProfileUpdateProfileTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test updateProfile updates user and user detail successfully.
     */
    public function test_update_profile_successful()
    {
        // Create a user
        $user = User::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'first_name' => 'Original',
            'last_name' => 'Name',
            'username' => 'originaluser',
            'email' => 'original@example.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'status' => 'active',
        ]);

        // Mock Auth::user() to return our created user
        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        // Prepare update input (social_media as JSON string to exercise decoding branch)
        $input = [
            'first_name' => 'UpdatedFirst',
            'last_name' => 'UpdatedLast',
            'username' => 'updateduser',
            'email' => 'updated@example.com',
            'telephone' => '08123456789',
            'expertise' => 'Testing',
            'about' => 'This is an updated about section.',
            'social_media' => json_encode(['twitter' => '@updated', 'linkedin' => 'updated-link']),
        ];

        $request = new Request($input);

        $controller = new UserProfileController();
        $response = $controller->updateProfile($request);

        // Assert HTTP 200
        $this->assertEquals(200, $response->getStatusCode());

        // Get response data as array (if JSON response)
        $responseData = $response->getData(true);

        // Assert message exists and matches expected text from controller
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Profile updated successfully', $responseData['message']);

        // Refresh user from DB and assert updated fields
        $user->refresh();
        $this->assertEquals('UpdatedFirst', $user->first_name);
        $this->assertEquals('UpdatedLast', $user->last_name);
        $this->assertEquals('updateduser', $user->username);
        $this->assertEquals('updated@example.com', $user->email);
        $this->assertEquals('08123456789', $user->telephone);

        // Assert detail was created/updated
        $user->load('detail');
        $this->assertNotNull($user->detail, 'User detail should exist after update');
        $this->assertEquals('Testing', $user->detail->expertise);
        $this->assertEquals('This is an updated about section.', $user->detail->about);
    }
}
