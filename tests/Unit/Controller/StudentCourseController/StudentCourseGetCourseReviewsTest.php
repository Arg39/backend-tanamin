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
use App\Models\CourseReview;
use App\Http\Controllers\Api\Course\StudentCourseController;

class StudentCourseGetCourseReviewsTest extends TestCase
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

    public function test_gets_course_reviews_successfully()
    {
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Reviews Cat',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Reviews',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Course With Reviews',
            'status' => 'published',
        ]);

        $userA = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'rev_a_' . Str::random(6),
            'first_name' => 'Alice',
            'last_name' => 'Reviewer',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $userB = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'rev_b_' . Str::random(6),
            'first_name' => 'Bob',
            'last_name' => 'Commenter',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // older review (created_at earlier)
        $rev1 = CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'user_id' => $userA->id,
            'rating' => 4,
            'comment' => 'Good course',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        // newer review
        $rev2 = CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'user_id' => $userB->id,
            'rating' => 5,
            'comment' => 'Excellent!',
            'created_at' => now()->subMinutes(1),
            'updated_at' => now()->subMinutes(1),
        ]);

        $controller = new StudentCourseController();
        $request = new Request();
        $response = $controller->getCourseReviews($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Course reviews retrieved successfully.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $returned = $data['data'];
        if (is_array($returned) && isset($returned['data'])) {
            $returned = $returned['data'];
        }

        $this->assertIsArray($returned);
        if (isset($returned['items'])) {
            $reviews = $returned['items'];
        } elseif (isset($returned['data'])) {
            $reviews = $returned['data'];
        } else {
            $this->fail('Paginated response missing items/data keys. Received: ' . json_encode($returned));
        }

        $this->assertIsArray($reviews);
        $this->assertCount(2, $reviews);

        // ordered by created_at desc => rev2 (userB) first
        $this->assertEquals($rev2->id, $reviews[0]['id']);
        $this->assertEquals($rev2->rating, $reviews[0]['rating']);
        $this->assertEquals($userB->id, $reviews[0]['user']['id']);

        $this->assertEquals($rev1->id, $reviews[1]['id']);
        $this->assertEquals($rev1->rating, $reviews[1]['rating']);
        $this->assertEquals($userA->id, $reviews[1]['user']['id']);
    }

    public function test_filters_reviews_by_name()
    {
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'FilterReviews Cat',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'FilterRev',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Filter Reviews Course',
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

        CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'user_id' => $matching->id,
            'rating' => 5,
            'comment' => 'Nice',
        ]);

        CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'user_id' => $other->id,
            'rating' => 3,
            'comment' => 'Ok',
        ]);

        $controller = new StudentCourseController();
        $request = new Request(['name' => 'MatchName']);
        $response = $controller->getCourseReviews($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('data', $data);
        $returned = $data['data'];
        if (is_array($returned) && isset($returned['data'])) {
            $returned = $returned['data'];
        }

        $this->assertIsArray($returned);
        if (isset($returned['items'])) {
            $reviews = $returned['items'];
        } elseif (isset($returned['data'])) {
            $reviews = $returned['data'];
        } else {
            $this->fail('Paginated response missing items/data keys. Received: ' . json_encode($returned));
        }

        $this->assertCount(1, $reviews);
        $this->assertEquals($matching->id, $reviews[0]['user']['id']);
    }

    public function test_filters_reviews_by_rating_single_and_csv()
    {
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'RatingFilter Cat',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Rating',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Rating Course',
            'status' => 'published',
        ]);

        $u1 = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'r1_' . Str::random(6),
            'first_name' => 'R1',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);
        $u2 = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'r2_' . Str::random(6),
            'first_name' => 'R2',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);
        $u3 = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'r3_' . Str::random(6),
            'first_name' => 'R3',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $revA = CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'user_id' => $u1->id,
            'rating' => 3,
            'comment' => 'Three',
        ]);
        $revB = CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'user_id' => $u2->id,
            'rating' => 4,
            'comment' => 'Four',
        ]);
        $revC = CourseReview::create([
            'id' => Str::uuid()->toString(),
            'course_id' => $course->id,
            'user_id' => $u3->id,
            'rating' => 5,
            'comment' => 'Five',
        ]);

        $controller = new StudentCourseController();

        // single rating filter (4)
        $requestSingle = new Request(['rating' => 4]);
        $responseSingle = $controller->getCourseReviews($requestSingle, $course->id);
        $dataSingle = $this->resolveResponseData($responseSingle, $requestSingle);

        $returned = $dataSingle['data'];
        if (is_array($returned) && isset($returned['data'])) {
            $returned = $returned['data'];
        }
        if (isset($returned['items'])) {
            $reviewsSingle = $returned['items'];
        } elseif (isset($returned['data'])) {
            $reviewsSingle = $returned['data'];
        } else {
            $this->fail('Paginated response missing items/data keys. Received: ' . json_encode($returned));
        }

        $this->assertCount(1, $reviewsSingle);
        $this->assertEquals(4, $reviewsSingle[0]['rating']);

        // csv rating filter (4,5)
        $requestCsv = new Request(['rating' => '4,5']);
        $responseCsv = $controller->getCourseReviews($requestCsv, $course->id);
        $dataCsv = $this->resolveResponseData($responseCsv, $requestCsv);

        $returned = $dataCsv['data'];
        if (is_array($returned) && isset($returned['data'])) {
            $returned = $returned['data'];
        }
        if (isset($returned['items'])) {
            $reviewsCsv = $returned['items'];
        } elseif (isset($returned['data'])) {
            $reviewsCsv = $returned['data'];
        } else {
            $this->fail('Paginated response missing items/data keys. Received: ' . json_encode($returned));
        }

        $this->assertCount(2, $reviewsCsv);
        $ratings = array_column($reviewsCsv, 'rating');
        sort($ratings);
        $this->assertEquals([4,5], $ratings);
    }

    public function test_returns_empty_when_no_reviews()
    {
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'EmptyReviews Cat',
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Empty',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'No Reviews Course',
            'status' => 'published',
        ]);

        $controller = new StudentCourseController();
        $request = new Request();
        $response = $controller->getCourseReviews($request, $course->id);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('data', $data);
        $returned = $data['data'];
        if (is_array($returned) && isset($returned['data'])) {
            $returned = $returned['data'];
        }

        $this->assertIsArray($returned);
        if (isset($returned['items'])) {
            $items = $returned['items'];
        } elseif (isset($returned['data'])) {
            $items = $returned['data'];
        } else {
            $this->fail('Paginated response missing items/data keys. Received: ' . json_encode($returned));
        }

        $this->assertIsArray($items);
        $this->assertCount(0, $items);
    }
}