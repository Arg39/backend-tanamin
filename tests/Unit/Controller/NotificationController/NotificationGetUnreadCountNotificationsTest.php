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

class NotificationGetUnreadCountNotificationsTest extends TestCase
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

    public function test_get_unread_count_returns_correct_count()
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

        // create 3 unread notifications
        $unreadCountExpected = 3;
        for ($i = 1; $i <= $unreadCountExpected; $i++) {
            Notification::createNotification($userId, "Unread Title {$i}", "Unread body {$i}");
        }

        // create 2 read notifications
        for ($i = 1; $i <= 2; $i++) {
            $n = Notification::createNotification($userId, "Read Title {$i}", "Read body {$i}");
            Notification::markAsRead($n->id);
        }

        // prepare request with authenticated user
        $request = new Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new NotificationController();
        $response = $controller->getUnreadCountNotifications($request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);

        // Accept either boolean true or string 'success' for status
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Unread notifications count retrieved', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        // normalize and assert unread_notifications key and value
        $it = is_object($data) ? (array) $data : $data;
        $this->assertIsArray($it);
        $this->assertArrayHasKey('unread_notifications', $it);
        $this->assertEquals($unreadCountExpected, $it['unread_notifications']);

        // double-check via model query
        $this->assertEquals($unreadCountExpected, Notification::where('user_id', $userId)->where('is_read', false)->count());
    }
}
