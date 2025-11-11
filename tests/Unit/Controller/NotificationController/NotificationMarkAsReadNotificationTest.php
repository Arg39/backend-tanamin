<?php

namespace Tests\Unit\Controller\NotificationController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Api\NotificationController;
use App\Models\Notification;
use App\Models\User;

class NotificationMarkAsReadNotificationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Normalize controller/resource response to array.
     */
    private function resolveResponseData($response, Request $request = null)
    {
        if (is_array($response)) {
            return $response;
        }

        if ($response instanceof JsonResponse) {
            return $response->getData(true);
        }

        if (is_object($response) && method_exists($response, 'toResponse')) {
            $httpResponse = $response->toResponse($request ?? new Request());
            if ($httpResponse instanceof JsonResponse) {
                return $httpResponse->getData(true);
            }
            if (method_exists($httpResponse, 'getData')) {
                return $httpResponse->getData(true);
            }
        }

        if (is_object($response) && method_exists($response, 'getData')) {
            return $response->getData(true);
        }

        throw new \RuntimeException('Unable to resolve response data in test. Response type: ' . gettype($response));
    }

    public function test_mark_notification_as_read_updates_flags_and_returns_notification()
    {
        $userId = (string) Str::uuid();

        // create user
        DB::table('users')->insert([
            'id' => $userId,
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser_' . Str::random(6),
            'email' => 'testuser+' . Str::random(6) . '@example.test',
            'password' => Hash::make('password'),
            'role' => 'student',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $user = User::find($userId);

        // create a notification (unread)
        $notification = Notification::createNotification($userId, 'Mark Read Title', 'Mark read body');

        $this->assertFalse($notification->is_read);

        // prepare request with authenticated user
        $request = new Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new NotificationController();
        $response = $controller->markAsReadNotification($request, $notification->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);

        // Accept either boolean true or string 'success' for status
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Notification marked as read', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        // normalize response data
        $it = is_object($data) ? (array) $data : $data;
        $this->assertIsArray($it);
        $this->assertArrayHasKey('id', $it);
        $this->assertEquals($notification->id, $it['id']);

        // check is_read and read_at in response
        $this->assertArrayHasKey('is_read', $it);
        $this->assertTrue((bool)$it['is_read']);

        $this->assertArrayHasKey('read_at', $it);
        $this->assertNotNull($it['read_at']);

        // verify persisted changes in DB
        $this->assertDatabaseHas('notification', [
            'id' => $notification->id,
            'is_read' => true,
        ]);

        $dbNotification = Notification::find($notification->id);
        $this->assertTrue($dbNotification->is_read);
        $this->assertNotNull($dbNotification->read_at);
    }

    public function test_mark_nonexistent_notification_returns_not_found()
    {
        $userId = (string) Str::uuid();

        // create user
        DB::table('users')->insert([
            'id' => $userId,
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser_' . Str::random(6),
            'email' => 'testuser+' . Str::random(6) . '@example.test',
            'password' => Hash::make('password'),
            'role' => 'student',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $user = User::find($userId);

        $request = new Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new NotificationController();
        $fakeId = (string) Str::uuid();
        $response = $controller->markAsReadNotification($request, $fakeId);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);

        // Accept either boolean false or string 'error'/'failed' for failure statuses
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status'], 'Expected status to be false');
        } else {
            $this->assertTrue(in_array($responseData['status'], ['error', 'failed']), 'Expected status string to be "error" or "failed"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Notification not found', $responseData['message']);
    }
}
