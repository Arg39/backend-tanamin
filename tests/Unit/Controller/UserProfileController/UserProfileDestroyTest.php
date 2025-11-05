<?php

namespace Tests\Unit\Controller\UserProfileController;

use App\Http\Controllers\Api\UserProfileController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileDestroyTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test that destroy deletes the user, their detail and files.
     */
    public function test_destroy_deletes_user_and_files()
    {
        // Fake the public disk
        Storage::fake('public');

        // Create user
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'First',
            'last_name' => 'Last',
            'username' => 'deleteuser',
            'email' => 'deleteuser@example.com',
            'password' => bcrypt('password123'),
            'role' => 'student',
            'status' => 'active',
            'photo_profile' => 'profile_photos/test_profile.jpg',
        ]);

        // Create detail with photo_cover
        $detail = $user->detail()->create([
            'user_id' => $user->id,
            'expertise' => 'Testing',
            'about' => 'About testing',
            'photo_cover' => 'cover_photos/test_cover.jpg',
        ]);

        // Ensure files exist on fake storage
        Storage::disk('public')->put($user->photo_profile, 'dummy');
        Storage::disk('public')->put($detail->photo_cover, 'dummy');

        $this->assertTrue(Storage::disk('public')->exists($user->photo_profile));
        $this->assertTrue(Storage::disk('public')->exists($detail->photo_cover));

        // Capture paths before deletion
        $profilePath = $user->photo_profile;
        $coverPath = $detail->photo_cover;

        // Call controller destroy
        $controller = new UserProfileController();
        $response = $controller->destroy($user->id);

        // Assert response
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = $response->getData(true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('User deleted successfully.', $responseData['message']);

        // Assert user and detail are removed from database
        $this->assertNull(User::find($user->id));
        $this->assertDatabaseMissing('user_details', ['user_id' => $user->id]);

        // Assert files deleted from fake storage
        $this->assertFalse(Storage::disk('public')->exists($profilePath));
        $this->assertFalse(Storage::disk('public')->exists($coverPath));
    }

    /**
     * Test that destroy returns 404 when user not found.
     */
    public function test_destroy_returns_404_when_user_not_found()
    {
        $randomId = Str::uuid()->toString();

        $controller = new UserProfileController();
        $response = $controller->destroy($randomId);

        $this->assertEquals(404, $response->getStatusCode());
        $responseData = $response->getData(true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('User not found.', $responseData['message']);
    }
}
