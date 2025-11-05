<?php

namespace Tests\Unit\Controller\OverviewCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Course;
use App\Models\Category;
use App\Models\User;
use App\Http\Controllers\Api\Course\OverviewCourseController;

class OverviewCourseShowTest extends TestCase
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

    public function test_show_returns_course_data_when_found()
    {
        // create required related records to satisfy FK constraints
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test Category',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Show',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            // ensure required foreign keys are present to avoid DB errors
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Test Course Show',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $controller = new OverviewCourseController();
        $request = new Request();
        $response = $controller->show($course->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Course retrieved successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];
        $this->assertIsArray($data);

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals($course->id, $data['id']);

        $this->assertArrayHasKey('title', $data);
        $this->assertEquals('Test Course Show', $data['title']);

        // discount should be present as null when not active
        $this->assertArrayHasKey('discount', $data);
        $this->assertNull($data['discount']);
    }

    public function test_show_returns_formatted_discount_percent_and_nominal()
    {
        // create related records to satisfy FK constraints
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Discount Category',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Discount',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // Percent discount
        $coursePercent = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Percent Discount Course',
            'price' => 100000,
            'is_discount_active' => true,
            'discount_type' => 'percent',
            'discount_value' => 10,
        ]);

        // Nominal discount
        $courseNominal = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Nominal Discount Course',
            'price' => 100000,
            'is_discount_active' => true,
            'discount_type' => 'nominal',
            'discount_value' => 50000,
        ]);

        $controller = new OverviewCourseController();
        $request = new Request();

        $responsePercent = $controller->show($coursePercent->id);
        $responseDataPercent = $this->resolveResponseData($responsePercent, $request);
        $this->assertArrayHasKey('data', $responseDataPercent);
        $this->assertEquals('10 %', $responseDataPercent['data']['discount']);

        $responseNominal = $controller->show($courseNominal->id);
        $responseDataNominal = $this->resolveResponseData($responseNominal, $request);
        $this->assertArrayHasKey('data', $responseDataNominal);
        $this->assertEquals('Rp. ' . number_format(50000, 0, ',', '.'), $responseDataNominal['data']['discount']);
    }

    public function test_show_returns_not_found_for_missing_course()
    {
        $fakeId = Str::uuid()->toString();

        $controller = new OverviewCourseController();
        $request = new Request();
        $response = $controller->show($fakeId);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        // expected false or some failure indicator
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status']);
        } else {
            // some implementations might return 'error' or similar; assert not success
            $this->assertNotEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Course not found or unauthorized access', $responseData['message']);
    }
}
