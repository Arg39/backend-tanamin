<?php

namespace Tests\Unit\Controller\CardCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Models\CourseEnrollment;
use App\Models\CourseCheckoutSession;
use App\Http\Controllers\Api\CardCourseController;

class CardCoursePurchaseHistoryTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Normalize controller response to array similar to other tests.
     */
    private function resolveResponseData($response, Request $request)
    {
        if (is_array($response)) {
            return $response;
        }

        if ($response instanceof JsonResponse) {
            return $response->getData(true);
        }

        if (is_object($response) && method_exists($response, 'toResponse')) {
            $httpResponse = $response->toResponse($request);
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

    public function test_purchase_history_unauthorized_returns_unauthorized_message()
    {
        $controller = new CardCourseController();
        $request = new Request();

        $response = $controller->purchaseHistory($request);
        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Unauthorized', $responseData['message']);

        // Accept either missing data or null
        $this->assertTrue(
            !array_key_exists('data', $responseData) || is_null($responseData['data']),
            'Expected "data" to be either absent or null when unauthorized.'
        );
    }

    public function test_purchase_history_returns_expected_entries()
    {
        // Arrange: create category, instructor and a student
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Gardening',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Teacher',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'student_' . Str::random(6),
            'first_name' => 'Stud',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // Create two courses
        $course1 = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Purchase Course 1',
            'price' => 75,
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        $course2 = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Purchase Course 2',
            'price' => 120,
            'level' => 'intermediate',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        // Create checkout sessions
        $checkout1Id = Str::uuid()->toString();
        $checkout2Id = Str::uuid()->toString();

        $date1 = Carbon::parse('2020-01-05 10:00:00');
        $date2 = Carbon::parse('2021-06-15 15:30:00');

        CourseCheckoutSession::create([
            'id' => $checkout1Id,
            'user_id' => $student->id,
            'payment_status' => 'paid',
            'created_at' => $date1,
            'updated_at' => $date1,
        ]);

        CourseCheckoutSession::create([
            'id' => $checkout2Id,
            'user_id' => $student->id,
            'payment_status' => 'paid',
            'created_at' => $date2,
            'updated_at' => $date2,
        ]);

        // Create enrollments referencing the checkout sessions
        $enroll1Id = Str::uuid()->toString();
        $enroll2Id = Str::uuid()->toString();

        CourseEnrollment::create([
            'id' => $enroll1Id,
            'user_id' => $student->id,
            'course_id' => $course1->id,
            'checkout_session_id' => $checkout1Id,
            'access_status' => 'active',
            'price' => 75,
            'created_at' => $date1,
            'updated_at' => $date1,
        ]);

        CourseEnrollment::create([
            'id' => $enroll2Id,
            'user_id' => $student->id,
            'course_id' => $course2->id,
            'checkout_session_id' => $checkout2Id,
            'access_status' => 'active',
            'price' => 120,
            'created_at' => $date2,
            'updated_at' => $date2,
        ]);

        // Act: call controller with authenticated student
        $controller = new CardCourseController();
        $request = new Request();
        $request->setUserResolver(function () use ($student) {
            return $student;
        });

        $response = $controller->purchaseHistory($request);
        $responseData = $this->resolveResponseData($response, $request);

        // Assert top-level
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Riwayat pembelian berhasil diambil.', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $payload = $responseData['data'];

        // Payload should contain 'data' (history array) and 'total'
        $this->assertArrayHasKey('data', $payload);
        $this->assertArrayHasKey('total', $payload);

        $history = $payload['data'];
        // Normalize objects to arrays if needed
        $history = array_map(function ($it) {
            return is_object($it) ? (array) $it : $it;
        }, is_array($history) ? $history : []);

        $this->assertCount(2, $history);
        $this->assertEquals(2, intval($payload['total']));

        // Helper to find entry by course id
        $findByCourse = function ($courseId) use ($history) {
            foreach ($history as $entry) {
                if (isset($entry['course_id']) && $entry['course_id'] === $courseId) {
                    return $entry;
                }
            }
            return null;
        };

        $entry1 = $findByCourse($course1->id);
        $this->assertNotNull($entry1, 'Entry for course1 should exist');
        $this->assertEquals($checkout1Id, $entry1['order_id']);
        $this->assertEquals($course1->title, $entry1['nama_course']);
        $this->assertEquals(75, $entry1['total']);
        $this->assertEquals('paid', $entry1['status']);
        $this->assertArrayHasKey('tanggal', $entry1);
        $this->assertIsString($entry1['tanggal']);
        $this->assertNotEmpty($entry1['tanggal']);

        $entry2 = $findByCourse($course2->id);
        $this->assertNotNull($entry2, 'Entry for course2 should exist');
        $this->assertEquals($checkout2Id, $entry2['order_id']);
        $this->assertEquals($course2->title, $entry2['nama_course']);
        $this->assertEquals(120, $entry2['total']);
        $this->assertEquals('paid', $entry2['status']);
        $this->assertArrayHasKey('tanggal', $entry2);
        $this->assertIsString($entry2['tanggal']);
        $this->assertNotEmpty($entry2['tanggal']);
    }
}
