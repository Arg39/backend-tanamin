<?php

namespace Tests\Unit\Controller\ReviewCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Mockery;
use App\Http\Controllers\Api\Course\ReviewCourseController;

class ReviewCourseGetRatingsCourseTest extends TestCase
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

    public function test_get_ratings_course_returns_ratings_successfully()
    {
        $fakeCourseId = Str::uuid()->toString();

        // create a fake Course-like object with avg_rating and getDetailRatings()
        $fakeRatings = [
            '1' => 1,
            '2' => 0,
            '3' => 2,
            '4' => 3,
            '5' => 4,
        ];
        $avg = 4.25;

        $fakeCourse = new class($avg, $fakeRatings) {
            public $avg_rating;
            private $detail;
            public function __construct($avg, $detail)
            {
                $this->avg_rating = $avg;
                $this->detail = $detail;
            }
            public function getDetailRatings()
            {
                return $this->detail;
            }
        };

        // Mock the Course model static find to return our fake object
        $courseMock = Mockery::mock('alias:App\Models\Course');
        $courseMock->shouldReceive('find')->with($fakeCourseId)->andReturn($fakeCourse);

        $controller = new ReviewCourseController();
        $request = new Request();
        $response = $controller->getRatingsCourse($fakeCourseId);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Ratings fetched successfully', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $payload = $data['data'];
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('rating', $payload);
        $this->assertEquals(round($avg, 1), $payload['rating']);
        $this->assertArrayHasKey('detail_rating', $payload);

        // compare values only (JSON encoding may reindex numeric keys)
        $this->assertEquals(array_values($fakeRatings), array_values($payload['detail_rating']));
    }

    public function test_get_ratings_course_not_found_returns_error()
    {
        $fakeCourseId = Str::uuid()->toString();

        // Mock Course::find to return null
        $courseMock = Mockery::mock('alias:App\Models\Course');
        $courseMock->shouldReceive('find')->with($fakeCourseId)->andReturn(null);

        $controller = new ReviewCourseController();
        $request = new Request();
        $response = $controller->getRatingsCourse($fakeCourseId);

        $data = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Course not found', $data['message']);
    }
}