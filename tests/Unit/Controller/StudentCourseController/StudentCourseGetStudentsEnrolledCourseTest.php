<?php

namespace Tests\Unit\Controller\StudentCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Models\CourseEnrollment;
use App\Models\CourseCheckoutSession as CheckoutSession;
use App\Http\Controllers\Api\Course\StudentCourseController;

class StudentCourseGetStudentsEnrolledCourseTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Normalize controller/resource response to array.
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

    public function test_returns_course_not_found_when_course_missing()
    {
        $controller = new StudentCourseController();
        $request = new Request();

        $fakeCourseId = Str::uuid()->toString();
        $response = $controller->getStudentsEnrolledCourse($request, $fakeCourseId);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Course not found.', $data['message']);
    }

    public function test_gets_students_enrolled_successfully()
    {
        // create required related records
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Enrolled Cat',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Show',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // create course published
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Enrolled Course',
            'status' => 'published',
        ]);

        // create users
        $user1 = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stu1_' . Str::random(6),
            'first_name' => 'First',
            'last_name' => 'Student',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $user2 = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stu2_' . Str::random(6),
            'first_name' => 'Second',
            'last_name' => 'Learner',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // create paid checkout sessions and enrollments
        $cs1Id = Str::uuid()->toString();
        $cs2Id = Str::uuid()->toString();

        // include required user_id to satisfy non-null DB constraint
        CheckoutSession::create([
            'id' => $cs1Id,
            'payment_status' => 'paid',
            'user_id' => $user1->id,
        ]);
        CheckoutSession::create([
            'id' => $cs2Id,
            'payment_status' => 'paid',
            'user_id' => $user2->id,
        ]);

        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'user_id' => $user1->id,
            'checkout_session_id' => $cs1Id,
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);

        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'user_id' => $user2->id,
            'checkout_session_id' => $cs2Id,
            'created_at' => now()->subMinutes(1),
            'updated_at' => now()->subMinutes(1),
        ]);

        $controller = new StudentCourseController();
        $request = new Request(); // default perPage
        $response = $controller->getStudentsEnrolledCourse($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Students retrieved successfully.', $data['message']);

        $this->assertArrayHasKey('data', $data);

        // unwrap possible extra 'data' wrapping (like AdminCourseShowTest)
        $returned = $data['data'];
        if (is_array($returned) && isset($returned['data'])) {
            $returned = $returned['data'];
        }

        // handle possible paginated shapes produced by TableResource
        $this->assertIsArray($returned);
        if (isset($returned['items'])) {
            $students = $returned['items'];
        } elseif (isset($returned['data'])) {
            $students = $returned['data'];
        } else {
            $this->fail('Paginated response missing items/data keys. Received: ' . json_encode($returned));
        }

        $this->assertIsArray($students);
        $this->assertCount(2, $students);

        // Accept any ordering from controller — verify the returned set matches expected students
        $returnedIds = array_column($students, 'id');
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], $returnedIds);

        foreach ($students as $s) {
            $this->assertArrayHasKey('id', $s);
            $this->assertArrayHasKey('email', $s);
            $this->assertArrayHasKey('name', $s);
            $this->assertArrayHasKey('full_name', $s);
            $this->assertArrayHasKey('photo_profile', $s);
            $this->assertArrayHasKey('enrolled_at', $s);
        }
    }

    public function test_filters_students_by_name()
    {
        // create required related records
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Filter Cat',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Filter',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Filter Course',
            'status' => 'published',
        ]);

        $matching = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'match_' . Str::random(6),
            'first_name' => 'MatchName',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $other = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'other_' . Str::random(6),
            'first_name' => 'Other',
            'last_name' => 'Student',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $csMatch = Str::uuid()->toString();
        $csOther = Str::uuid()->toString();

        // include user_id to satisfy non-null DB constraint
        CheckoutSession::create([
            'id' => $csMatch,
            'payment_status' => 'paid',
            'user_id' => $matching->id,
        ]);
        CheckoutSession::create([
            'id' => $csOther,
            'payment_status' => 'paid',
            'user_id' => $other->id,
        ]);

        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'user_id' => $matching->id,
            'checkout_session_id' => $csMatch,
        ]);

        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'user_id' => $other->id,
            'checkout_session_id' => $csOther,
        ]);

        $controller = new StudentCourseController();
        $request = new Request(['name' => 'MatchName']);
        $response = $controller->getStudentsEnrolledCourse($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('data', $data);

        // unwrap possible extra 'data' wrapping
        $returned = $data['data'];
        if (is_array($returned) && isset($returned['data'])) {
            $returned = $returned['data'];
        }

        // handle possible paginated shapes produced by TableResource
        $this->assertIsArray($returned);
        if (isset($returned['items'])) {
            $students = $returned['items'];
        } elseif (isset($returned['data'])) {
            $students = $returned['data'];
        } else {
            $this->fail('Paginated response missing items/data keys. Received: ' . json_encode($returned));
        }

        $this->assertIsArray($students);
        $this->assertCount(1, $students);
        $this->assertEquals($matching->id, $students[0]['id']);
    }
}
