<?php

namespace Tests\Unit\Controller\FilteringCardController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\Models\CourseReview;
use App\Http\Controllers\Api\Course\FilteringCardController;

class FilteringCardGetFilteringCardTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_get_filtering_card_returns_expected_structure_and_counts()
    {
        // Create categories
        $catA = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Category A',
            'image' => null,
        ]);

        $catB = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Category B',
            'image' => null,
        ]);

        // Create instructors
        $instWithPublished = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'inst_with_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'One',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $instWithoutPublished = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'inst_no_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Two',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // Create student users to use for reviews (avoid duplicate user+course inserts)
        $student1 = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'student1_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'One',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'user',
        ]);

        $student2 = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'student2_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Two',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'user',
        ]);

        // Create courses:
        // - published, free, beginner -> catA, instWithPublished
        $course1 = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $catA->id,
            'instructor_id' => $instWithPublished->id,
            'title' => 'Course 1',
            'price' => 0,
            'level' => 'beginner',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        // - published, paid, intermediate -> catB, instWithPublished
        $course2 = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $catB->id,
            'instructor_id' => $instWithPublished->id,
            'title' => 'Course 2',
            'price' => 150,
            'level' => 'intermediate',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        // - published, paid, advance -> catA, instWithPublished (to create advance count)
        $course3 = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $catA->id,
            'instructor_id' => $instWithPublished->id,
            'title' => 'Course 3',
            'price' => 200,
            'level' => 'advance',
            'image' => null,
            'status' => 'published',
            'detail' => null,
        ]);

        // - draft course for instWithoutPublished (should NOT count for published instructor list)
        $draftCourse = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $catB->id,
            'instructor_id' => $instWithoutPublished->id,
            'title' => 'Draft Course',
            'price' => 0,
            'level' => 'beginner',
            'image' => null,
            // Use 'new' (default in Course::boot) instead of 'draft' to match DB enum
            'status' => 'new',
            'detail' => null,
        ]);

        // Create course reviews to populate rating counts:
        // one 5-star (for course1), two 4-star (for course2 by two different users), one 1-star (for course3)
        CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course1->id,
            'user_id' => $student1->id,
            'rating' => 5,
            'comment' => 'Great',
        ]);

        CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course2->id,
            'user_id' => $student1->id,
            'rating' => 4,
            'comment' => 'Good',
        ]);

        // Use a different user for the second review on course2 to avoid unique constraint violation
        CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course2->id,
            'user_id' => $student2->id,
            'rating' => 4,
            'comment' => 'Nice',
        ]);

        CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course3->id,
            'user_id' => $student1->id,
            'rating' => 1,
            'comment' => 'Bad',
        ]);

        $controller = new FilteringCardController();
        $request = new Request();
        $response = $controller->getFilteringCard();

        $responseData = $this->resolveResponseData($response, $request);

        // Basic structure
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('data', $responseData);

        $data = $responseData['data'];

        // category & published_courses_count checks
        $this->assertArrayHasKey('category', $data);
        $this->assertIsArray($data['category']);

        // Build a map of category id => published_courses_count from response
        $catMap = [];
        foreach ($data['category'] as $c) {
            $catMap[$c['id']] = $c['published_courses_count'];
        }

        $this->assertArrayHasKey($catA->id, $catMap);
        $this->assertArrayHasKey($catB->id, $catMap);

        // catA has course1 and course3 published -> 2
        $this->assertEquals(2, $catMap[$catA->id]);
        // catB has course2 published -> 1
        $this->assertEquals(1, $catMap[$catB->id]);

        // instructor checks: only instWithPublished should appear
        $this->assertArrayHasKey('instructor', $data);
        $this->assertIsArray($data['instructor']);

        $instrIds = array_column($data['instructor'], 'id');
        $this->assertContains($instWithPublished->id, $instrIds);
        $this->assertNotContains($instWithoutPublished->id, $instrIds);

        // Check published_courses_count for instructor
        $found = null;
        foreach ($data['instructor'] as $ins) {
            if ($ins['id'] === $instWithPublished->id) {
                $found = $ins;
                break;
            }
        }
        $this->assertNotNull($found, 'Expected instructor with published courses in result.');
        // instWithPublished has 3 published courses (course1, course2, course3)
        $this->assertEquals(3, $found['published_courses_count']);

        // rating checks: response provides array of ratings 5..1
        $this->assertArrayHasKey('rating', $data);
        $this->assertIsArray($data['rating']);

        // Convert rating list to map rating => total
        $ratingMap = [];
        foreach ($data['rating'] as $r) {
            $ratingMap[$r['rating']] = $r['total'];
        }

        // Compute expected counts from reviews we created (only for the courses created in this test)
        $expectedRatings = CourseReview::whereIn('course_id', [$course1->id, $course2->id, $course3->id])
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->toArray();

        // Ensure the controller response includes at least the reviews we created (there may be other reviews in DB)
        for ($i = 5; $i >= 1; $i--) {
            $expected = isset($expectedRatings[$i]) ? (int)$expectedRatings[$i] : 0;
            $actual = isset($ratingMap[$i]) ? (int)$ratingMap[$i] : 0;
            $this->assertGreaterThanOrEqual($expected, $actual, "Expected response rating {$i} total ({$actual}) to be >= created count ({$expected}).");
        }

        // price checks
        $this->assertArrayHasKey('price', $data);
        $this->assertIsArray($data['price']);
        $this->assertNotEmpty($data['price']);
        $priceObj = $data['price'][0];
        $this->assertArrayHasKey('free', $priceObj);
        $this->assertArrayHasKey('paid', $priceObj);

        // Instead of asserting exact totals (which can be affected by other DB data),
        // compute expected free/paid counts from the courses created in this test and
        // ensure the response includes at least those counts.
        $createdCourseIds = [$course1->id, $course2->id, $course3->id, $draftCourse->id];
        $expectedFree = Course::whereIn('id', $createdCourseIds)->where('price', 0)->count();
        $expectedPaid = Course::whereIn('id', $createdCourseIds)->where('price', '>', 0)->count();

        $this->assertGreaterThanOrEqual($expectedFree, $priceObj['free'], "Expected response free courses ({$priceObj['free']}) to be >= created free count ({$expectedFree}).");
        $this->assertGreaterThanOrEqual($expectedPaid, $priceObj['paid'], "Expected response paid courses ({$priceObj['paid']}) to be >= created paid count ({$expectedPaid}).");

        // level checks
        $this->assertArrayHasKey('level', $data);
        $this->assertIsArray($data['level']);

        // level counts: from created courses (controller counts whereNotNull('level') across all courses, no status filter)
        // We created: beginner -> course1 + draftCourse = 2; intermediate -> course2 =1; advance -> course3 =1

        // Compute expected level totals from the created courses and assert response includes at least those counts.
        $expectedLevels = Course::whereIn('id', $createdCourseIds)
            ->whereNotNull('level')
            ->selectRaw('level, COUNT(*) as total')
            ->groupBy('level')
            ->pluck('total', 'level')
            ->toArray();

        $levels = ['beginner', 'intermediate', 'advance'];
        foreach ($levels as $lvl) {
            $expected = isset($expectedLevels[$lvl]) ? (int)$expectedLevels[$lvl] : 0;
            $actual = isset($data['level'][$lvl]) ? (int)$data['level'][$lvl] : 0;
            $this->assertGreaterThanOrEqual($expected, $actual, "Expected response level {$lvl} total ({$actual}) to be >= created count ({$expected}).");
        }
    }
}
