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
use App\Models\CourseReview;
use App\Http\Controllers\Api\CardCourseController;

class CardCourseIndexTest extends TestCase
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

    public function test_index_returns_published_courses_with_ratings_and_fields()
    {
        // Arrange: create category, instructor and courses
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Gardening',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'One',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // Create a separate reviewer user so a course can have multiple reviews from different users
        $reviewer = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'rev_' . Str::random(6),
            'first_name' => 'Rev',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // published courses
        $courseA = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course A',
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
            'title' => 'Course B',
            'price' => 100,
            'level' => 'intermediate',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        // unpublished course (should be filtered out)
        $courseUnpub = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Hidden Course',
            'price' => 50,
            'level' => 'intermediate',
            'image' => null,
            'status' => 'new',
            'detail' => null,
        ]);

        // Add reviews for courseA to compute average_rating and total_rating
        CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $courseA->id,
            'user_id' => $instructor->id,
            'rating' => 5,
            'comment' => 'Great',
        ]);
        CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $courseA->id,
            'user_id' => $reviewer->id, // use different user to avoid unique constraint
            'rating' => 3,
            'comment' => 'Good',
        ]);

        // dd(CourseReview::all());
        // Act: call controller index
        $controller = new CardCourseController();
        $request = new Request(); // default params
        $response = $controller->index($request);

        $responseData = $this->resolveResponseData($response, $request);

        // Assert: top-level structure and find items inside nested data
        $this->assertArrayHasKey('data', $responseData, 'Expected top-level "data" key in response');

        $items = $this->extractItems($responseData['data']);
        $this->assertNotEmpty($items, 'Expected to find course items in response');

        // Ensure unpublished course not present and published ones are present
        $ids = array_map(function ($it) {
            return $it['id'] ?? ($it['course_id'] ?? null);
        }, $items);
        $this->assertContains($courseA->id, $ids);
        $this->assertContains($courseB->id, $ids);
        $this->assertNotContains($courseUnpub->id, $ids);

        // Find courseA item and assert rating fields
        $foundA = null;
        foreach ($items as $it) {
            if (($it['id'] ?? null) === $courseA->id) {
                $foundA = $it;
                break;
            }
        }
        $this->assertNotNull($foundA, 'Course A item should be present');

        $this->assertArrayHasKey('average_rating', $foundA);
        $this->assertArrayHasKey('total_rating', $foundA);
        $this->assertEquals(2, intval($foundA['total_rating']));
        // average should be (5+3)/2 = 4.00 (rounded to 2 decimals in controller)
        $this->assertEquals(4.0, floatval($foundA['average_rating']));

        // Ensure flags exist (owned/bookmark) even when no auth (should be false)
        $this->assertArrayHasKey('owned', $foundA);
        $this->assertArrayHasKey('bookmark', $foundA);
        $this->assertFalse(boolval($foundA['owned']));
        $this->assertFalse(boolval($foundA['bookmark']));
    }

    public function test_index_respects_per_page_parameter()
    {
        // Arrange: create category, instructor and two published courses
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Gardening',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Two',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Page Course 1',
            'price' => 0,
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Page Course 2',
            'price' => 100,
            'level' => 'intermediate',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        // Act: request with per_page = 1
        $controller = new CardCourseController();
        $request = new Request(['per_page' => 1, 'page' => 1]);
        $response = $controller->index($request);

        $responseData = $this->resolveResponseData($response, $request);
        $this->assertArrayHasKey('data', $responseData);

        $items = $this->extractItems($responseData['data']);
        // exactly 1 item returned per page
        $this->assertCount(1, $items, 'Expected exactly 1 course item due to per_page=1');

        // Ensure pagination metadata exists somewhere in response->data (we expect paginator serialization)
        $foundPagination = false;
        $queue = [$responseData['data']];
        while (!empty($queue)) {
            $node = array_shift($queue);
            if (!is_array($node)) continue;
            if (isset($node['current_page']) && isset($node['last_page'])) {
                $foundPagination = true;
                break;
            }
            foreach ($node as $child) {
                if (is_array($child)) $queue[] = $child;
            }
        }
        $this->assertTrue($foundPagination, 'Expected pagination metadata in response data');
    }
}
