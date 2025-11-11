<?php

namespace Tests\Unit\Controller\DashboardController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use App\Http\Controllers\Api\DashboardController;

class DashboardGetInstructorTest extends TestCase
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

    protected function tearDown(): void
    {
        if (class_exists(\Mockery::class)) {
            \Mockery::close();
        }
        parent::tearDown();
    }

    public function test_getInstructor_returns_list_ordered_by_published_courses()
    {
        // create a category for courses
        $cat = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Category For Instructors',
        ]);

        // create instructors with known ids
        $instrA = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instrA_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'A',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);
        $instrB = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instrB_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'B',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);
        $instrC = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instrC_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'C',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // create a non-instructor user to ensure filtering
        $other = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'other_' . Str::random(6),
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        // instrA: 3 published, 1 draft
        Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat->id,
            'instructor_id' => $instrA->id,
            'title' => 'A Pub 1',
            'price' => 10000,
            'is_discount_active' => false,
            'status' => 'published',
        ]);
        Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat->id,
            'instructor_id' => $instrA->id,
            'title' => 'A Pub 2',
            'price' => 10000,
            'is_discount_active' => false,
            'status' => 'published',
        ]);
        Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat->id,
            'instructor_id' => $instrA->id,
            'title' => 'A Pub 3',
            'price' => 10000,
            'is_discount_active' => false,
            'status' => 'published',
        ]);
        Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat->id,
            'instructor_id' => $instrA->id,
            'title' => 'A Draft',
            'price' => 10000,
            'is_discount_active' => false,
            'status' => 'new',
        ]);

        // instrB: 2 published
        Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat->id,
            'instructor_id' => $instrB->id,
            'title' => 'B Pub 1',
            'price' => 15000,
            'is_discount_active' => false,
            'status' => 'published',
        ]);
        Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat->id,
            'instructor_id' => $instrB->id,
            'title' => 'B Pub 2',
            'price' => 15000,
            'is_discount_active' => false,
            'status' => 'published',
        ]);

        // instrC: 1 published, 1 awaiting_approval
        Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat->id,
            'instructor_id' => $instrC->id,
            'title' => 'C Pub 1',
            'price' => 20000,
            'is_discount_active' => false,
            'status' => 'published',
        ]);
        Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $cat->id,
            'instructor_id' => $instrC->id,
            'title' => 'C Await',
            'price' => 20000,
            'is_discount_active' => false,
            'status' => 'awaiting_approval',
        ]);

        $controller = new DashboardController();
        $request = new Request();
        $response = $controller->getInstructor();

        $data = $this->resolveResponseData($response, $request);

        // status and message
        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }
        $this->assertArrayHasKey('message', $data);

        // data payload checks
        $this->assertArrayHasKey('data', $data);
        $payload = $data['data'];

        // should return only instructors and preserve order by published_courses_count desc
        $this->assertIsArray($payload);
        $this->assertCount(3, $payload);

        // first should be instrA (3 published), second instrB (2), third instrC (1)
        $this->assertArrayHasKey('id', $payload[0]);
        $this->assertArrayHasKey('id', $payload[1]);
        $this->assertArrayHasKey('id', $payload[2]);

        $this->assertEquals($instrA->id, $payload[0]['id']);
        $this->assertEquals($instrB->id, $payload[1]['id']);
        $this->assertEquals($instrC->id, $payload[2]['id']);

        // published counts (if present) should match expected values
        if (isset($payload[0]['published_courses_count'])) {
            $this->assertEquals(3, (int) $payload[0]['published_courses_count']);
            $this->assertEquals(2, (int) $payload[1]['published_courses_count']);
            $this->assertEquals(1, (int) $payload[2]['published_courses_count']);
        }
    }
}
