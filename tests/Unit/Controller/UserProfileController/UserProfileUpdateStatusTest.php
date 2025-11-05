<?php

namespace Tests\Unit\Controller\UserProfileController;

use App\Http\Controllers\Api\UserProfileController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserProfileUpdateStatusTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test updateStatus updates user status successfully.
     */
    public function test_update_status_successful()
    {
        // Create a user with active status
        $user = User::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'first_name' => 'Alice',
            'last_name' => 'Example',
            'username' => 'aliceexample',
            'email' => 'alice@example.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'status' => 'active',
        ]);

        // Prepare request to change status to inactive
        $request = new Request([
            'status' => 'inactive',
        ]);

        $controller = new UserProfileController();
        $response = $controller->updateStatus($request, $user->id);

        // Assert HTTP 200
        $this->assertEquals(200, $response->getStatusCode());

        $responseData = $response->getData(true);

        // Assert response structure and message
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('User status updated successfully.', $responseData['message']);
        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('success', $responseData['status']);

        // Refresh user and assert status changed
        $user->refresh();
        $this->assertEquals('inactive', $user->status);
    }

    /**
     * Test updateStatus returns validation error for invalid status.
     */
    public function test_update_status_validation_error()
    {
        // Create a user
        $user = User::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'first_name' => 'Bob',
            'last_name' => 'Example',
            'username' => 'bobexample',
            'email' => 'bob@example.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'status' => 'active',
        ]);

        // Invalid status value
        $request = new Request([
            'status' => 'invalid_status_value',
        ]);

        $controller = new UserProfileController();
        $response = $controller->updateStatus($request, $user->id);

        // Assert HTTP 422 for validation error
        $this->assertEquals(422, $response->getStatusCode());

        $responseData = $response->getData(true);

        // Assertions matching actual controller response for validation error
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Validation errors', $responseData['message']);

        $this->assertArrayHasKey('status', $responseData);
        // controller returns 'failed' on validation errors (observed)
        $this->assertEquals('failed', $responseData['status']);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('status', $responseData['data']);
        $this->assertIsArray($responseData['data']['status']);
        $this->assertNotEmpty($responseData['data']['status']);
        // Optionally check the validation message text (may vary by locale)
        $this->assertStringContainsString('invalid', strtolower($responseData['data']['status'][0]));
    }
}
