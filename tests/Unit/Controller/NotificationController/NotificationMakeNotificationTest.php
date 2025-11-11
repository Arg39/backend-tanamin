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

class NotificationMakeNotificationTest extends TestCase
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

    public function test_make_notification_creates_notification_successfully()
    {
        $userId = (string) Str::uuid();
        $title = 'Test Notification Title';
        $body = 'This is the body of the test notification.';

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

        $controller = new NotificationController();
        $response = $controller->makeNotification($userId, $title, $body);

        $responseData = $this->resolveResponseData($response, new Request());

        $this->assertArrayHasKey('status', $responseData);

        // Accept either boolean true or string 'success' for status
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Notification created successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        // Normalize resource data to array if object
        $it = is_object($data) ? (array) $data : $data;
        $this->assertIsArray($it);
        $this->assertArrayHasKey('id', $it);
        $this->assertArrayHasKey('title', $it);
        $this->assertArrayHasKey('body', $it);
        $this->assertEquals($title, $it['title']);
        $this->assertEquals($body, $it['body']);

        // Verify persisted in DB (table name 'notification' per model)
        $this->assertDatabaseHas('notification', [
            'id' => $it['id'],
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
        ]);
    }
}
