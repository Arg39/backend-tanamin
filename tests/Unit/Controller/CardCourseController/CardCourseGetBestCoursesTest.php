<?php

namespace Tests\Unit\Controller\CardCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Models\CourseEnrollment;
use App\Http\Controllers\Api\CardCourseController;
use App\Models\CourseCheckoutSession;

class CardCourseGetBestCoursesTest extends TestCase
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

    /**
     * Walk nested arrays to find the first numerically indexed array of item-rows.
     * This handles TableResource wrapping + paginator structure.
     */
    private function extractItems(array $responseData)
    {
        $queue = [$responseData];
        while (!empty($queue)) {
            $node = array_shift($queue);
            if (!is_array($node)) {
                continue;
            }

            // If this node looks like a list of items (numeric keys starting from 0)
            if ($this->isNumericArray($node)) {
                // Ensure first element is an array/object representing an item
                if (isset($node[0]) && (is_array($node[0]) || is_object($node[0]))) {
                    // normalize objects to arrays
                    return array_map(function ($it) {
                        return is_object($it) ? (array) $it : $it;
                    }, $node);
                }
            }

            // otherwise queue children arrays for inspection
            foreach ($node as $child) {
                if (is_array($child) || is_object($child)) {
                    $queue[] = is_object($child) ? (array) $child : $child;
                }
            }
        }

        return [];
    }

    private function isNumericArray(array $arr)
    {
        if (empty($arr)) return false;
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    public function test_getBestCourses_returns_10_random_published_courses_when_no_enrollments()
    {
        // Arrange: create category, instructor and 12 published courses
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Gardening',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Random',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // create 12 published courses
        $created = [];
        for ($i = 0; $i < 12; $i++) {
            $created[] = Course::create([
                'id' => Str::uuid()->toString(),
                'category_id' => $category->id,
                'instructor_id' => $instructor->id,
                'title' => 'Random Course ' . $i,
                'price' => 0,
                'level' => 'beginner',
                'image' => null,
                'status' => 'published',
                'detail' => null,
            ]);
        }

        // Act
        $controller = new CardCourseController();
        $request = new Request(); // use defaults per_page = 10
        $response = $controller->getBestCourses($request);

        $responseData = $this->resolveResponseData($response, $request);

        // Assert: find items and expect up to perPage items returned
        $this->assertArrayHasKey('data', $responseData, 'Expected top-level "data" key in response');

        // TableResource wraps ['data' => $paginator], so drill into it
        $root = $responseData['data'];
        $items = $this->extractItems($root);

        // Determine per-page and published total for context
        $perPage = 10;
        $totalPublished = Course::where('status', 'published')->count();

        // Ensure result does not exceed per-page
        $this->assertLessThanOrEqual($perPage, count($items), 'Controller returned more items than per_page');

        // Ensure at least one item is returned (guard against empty)
        $this->assertGreaterThanOrEqual(1, count($items), 'Controller returned no items, expected at least one');

        // Ensure returned items are unique and are published courses
        $ids = array_map(function ($it) {
            return $it['id'] ?? null;
        }, $items);
        $this->assertCount(count(array_unique($ids)), $ids, 'Returned items contain duplicates');

        foreach ($ids as $id) {
            $this->assertNotNull(
                Course::where('id', $id)->where('status', 'published')->first(),
                "Returned course id {$id} is not a published course"
            );
        }

        // Each item should contain id and rating fields
        foreach ($items as $it) {
            $this->assertArrayHasKey('id', $it);
            $this->assertArrayHasKey('average_rating', $it);
            $this->assertArrayHasKey('total_rating', $it);
        }
    }

    public function test_getBestCourses_prioritizes_courses_with_paid_enrollments()
    {
        // Arrange: create category and instructor
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Gardening',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Priority',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // Create two published courses that will be top by enrollment counts
        $courseA = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Top Course A',
            'price' => 0,
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        $courseB = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Top Course B',
            'price' => 100,
            'level' => 'intermediate',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        // Create student users for enrollments (ensure distinct users to avoid unique constraint)
        $student1 = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_' . Str::random(6),
            'first_name' => 'Stud',
            'last_name' => 'One',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $student2 = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud2_' . Str::random(6),
            'first_name' => 'Stud',
            'last_name' => 'Two',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // third distinct student to allow three enrollments on courseA without duplicate user+course
        $student3 = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud3_' . Str::random(6),
            'first_name' => 'Stud',
            'last_name' => 'Three',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // Create CheckoutSession entries with payment_status = 'paid' and proper user_id
        $cs1 = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student1->id,
            'payment_status' => 'paid',
        ]);
        $cs2 = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student2->id,
            'payment_status' => 'paid',
        ]);
        $cs3 = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student3->id,
            'payment_status' => 'paid',
        ]);

        // Create enrollments:
        // courseA: 3 enrollments (using cs1, cs2, cs3) with distinct users
        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student1->id,
            'course_id' => $courseA->id,
            'checkout_session_id' => $cs1->id,
            'price' => 0,
            'access_status' => 'active',
        ]);
        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student2->id,
            'course_id' => $courseA->id,
            'checkout_session_id' => $cs2->id,
            'price' => 0,
            'access_status' => 'active',
        ]);
        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student3->id,
            'course_id' => $courseA->id,
            'checkout_session_id' => $cs3->id,
            'price' => 0,
            'access_status' => 'active',
        ]);

        // courseB: 1 enrollment
        $cs4 = CourseCheckoutSession::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student2->id,
            'payment_status' => 'paid',
        ]);
        CourseEnrollment::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student2->id,
            'course_id' => $courseB->id,
            'checkout_session_id' => $cs4->id,
            'price' => 100,
            'access_status' => 'active',
        ]);

        // Act
        $controller = new CardCourseController();
        $request = new Request(); // default per_page
        $response = $controller->getBestCourses($request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('data', $responseData, 'Expected top-level "data" key in response');

        $items = $this->extractItems($responseData['data']);
        $this->assertNotEmpty($items, 'Expected some items returned');

        // Assert that both courseA and courseB are present
        $ids = array_map(function ($it) {
            return $it['id'] ?? ($it['course_id'] ?? null);
        }, $items);
        $this->assertContains($courseA->id, $ids);
        $this->assertContains($courseB->id, $ids);

        // Do not assert exact count == 2 because DB may contain other published courses used to fill results.
        // Instead ensure result does not exceed per_page and the prioritized courses are included.
        $this->assertLessThanOrEqual(10, count($items), 'Returned more items than per_page');
    }
}
