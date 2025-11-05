<?php

namespace Tests\Unit\Controller\UserProfileController;

use App\Http\Controllers\Api\UserProfileController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserProfileUpdatePasswordTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test successful password update sets the new password and updates detail.update_password to false.
     */
    public function test_update_password_success_sets_password_and_detail_flag_false()
    {
        // Create user with detail that has update_password = true
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'First',
            'last_name' => 'Last',
            'username' => 'pwuser',
            'email' => 'pwuser@example.com',
            'password' => bcrypt('oldpassword'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $user->detail()->create([
            'user_id' => $user->id,
            'expertise' => 'Testing',
            'about' => 'About testing',
            'update_password' => true,
        ]);

        // Mock Auth::user() to return our created user
        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $controller = new UserProfileController();

        $request = Request::create('/update-password', 'POST', [
            'password' => 'newpassword123',
        ]);

        $response = $controller->updatePassword($request);

        $this->assertEquals(200, $response->getStatusCode());
        $responseData = $response->getData(true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Password updated successfully.', $responseData['message']);

        // Refresh user from DB and assert password changed
        $reloaded = User::find($user->id);
        $this->assertTrue(Hash::check('newpassword123', $reloaded->password));

        // Assert detail.update_password was set to false
        $this->assertNotNull($reloaded->detail);
        $this->assertFalse((bool) $reloaded->detail->update_password);
    }

    /**
     * Test that updatePassword returns 422 when validation fails (missing password).
     */
    public function test_update_password_validation_error_returns_422()
    {
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'First',
            'last_name' => 'Last',
            'username' => 'pwuser2',
            'email' => 'pwuser2@example.com',
            'password' => bcrypt('oldpassword'),
            'role' => 'student',
            'status' => 'active',
        ]);

        Auth::shouldReceive('user')
            ->once()
            ->andReturn($user);

        $controller = new UserProfileController();

        // No password provided
        $request = Request::create('/update-password', 'POST', []);

        $response = $controller->updatePassword($request);

        $this->assertEquals(422, $response->getStatusCode());
        $responseData = $response->getData(true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Validation errors', $responseData['message']);
    }
}
