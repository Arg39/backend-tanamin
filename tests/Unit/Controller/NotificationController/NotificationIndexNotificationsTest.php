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

class NotificationIndexNotificationsTest extends TestCase
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

    public function test_index_notifications_returns_all_notifications_ordered_and_with_expected_fields()
    {
        // Prepare user
        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'first_name' => 'Index',
            'last_name' => 'Tester',
            'username' => 'indextester_' . Str::random(6),
            'email' => 'indextester+' . Str::random(6) . '@example.test',
            'password' => Hash::make('password'),
            'role' => 'student',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $user = User::find($userId);

        // Create notifications (3 total), mark one as read
        $n1 = Notification::createNotification($userId, 'Title 1', 'Body 1'); // will later be oldest
        $n2 = Notification::createNotification($userId, 'Title 2', 'Body 2'); // middle
        $n3 = Notification::createNotification($userId, 'Title 3', 'Body 3'); // newest

        // Mark n2 as read to ensure read flag appears
        Notification::markAsRead($n2->id);

        // Set deterministic created_at values to assert ordering (n3 newest -> n1 oldest)
        $now = Carbon::now();
        $n1->created_at = $now->subMinutes(3);
        $n1->save();
        $n2->created_at = $now->addMinutes(1)->subMinutes(2); // ensure middle
        $n2->save();
        $n3->created_at = $now->addMinutes(2); // newest
        $n3->save();

        // Prepare request with authenticated user
        $request = new Request();
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $controller = new NotificationController();
        $response = $controller->indexNotifications($request);

        $responseData = $this->resolveResponseData($response, $request);

        // Basic response shape assertions
        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Notifications retrieved', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        // Normalize data and notifications list
        $it = is_object($data) ? (array) $data : $data;
        $this->assertIsArray($it);
        $this->assertArrayHasKey('notifications', $it);

        $notifications = $it['notifications'];
        // If returned as collection/object, convert to array
        if (is_object($notifications) && method_exists($notifications, 'toArray')) {
            $notifications = $notifications->toArray();
        }
        $this->assertIsArray($notifications);

        // Expect 3 notifications returned
        $this->assertCount(3, $notifications);

        // Check each notification fields and collect created_at timestamps for order check
        $createdAtList = [];
        foreach ($notifications as $idx => $notif) {
            // If resource returned single-level objects convert to array
            $item = is_object($notif) ? (array) $notif : $notif;
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('body', $item);
            $this->assertArrayHasKey('is_read', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('read_at', $item);

            // Basic content checks for known titles/bodies presence
            $this->assertStringStartsWith('Title', $item['title']);
            $this->assertStringStartsWith('Body', $item['body']);

            // collect created_at as timestamp for ordering assertion
            $createdAtList[] = strtotime($item['created_at']);
        }

        // Assert ordering is descending (newest first)
        for ($i = 0; $i < count($createdAtList) - 1; $i++) {
            $this->assertGreaterThanOrEqual($createdAtList[$i + 1], $createdAtList[$i], 'Notifications are expected in descending created_at order');
        }

        // Spot-check persisted DB record for one notification
        $firstNotif = is_object($notifications[0]) ? (array) $notifications[0] : $notifications[0];
        $this->assertDatabaseHas('notification', [
            'id' => $firstNotif['id'],
            'user_id' => $userId,
        ]);
    }
}
