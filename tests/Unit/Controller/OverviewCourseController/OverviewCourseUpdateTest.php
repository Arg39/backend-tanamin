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
use App\Http\Requests\UpdateCourseOverviewRequest;

class OverviewCourseUpdateTest extends TestCase
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

    public function test_update_updates_course_summary_fields()
    {
        // create required related records to satisfy FK constraints
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Update Category',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Update',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // create initial course
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Original Title',
            'price' => null,
            'is_discount_active' => false,
            'level' => 'beginner',
            'status' => 'new',
            'detail' => 'Original detail',
        ]);

        // prepare update payload
        $newTitle = 'Updated Course Title';
        $newLevel = 'intermediate';
        $newStatus = 'edited';
        $newDetail = '<p>Updated detail content</p>';

        // instantiate the form request and populate data
        $request = new UpdateCourseOverviewRequest();
        // merge input data into the request (FormRequest extends Request)
        $request->merge([
            'title' => $newTitle,
            'level' => $newLevel,
            'status' => $newStatus,
            'detail' => $newDetail,
        ]);

        // Ensure FormRequest->validated() works when invoked inside controller:
        // create a tiny validator-like object returning the merged data, then
        // inject it into the protected $validator property using reflection.
        $validatorStub = new class($request->all()) {
            private $data;
            public function __construct($data)
            {
                $this->data = $data;
            }
            public function validated()
            {
                return $this->data;
            }
        };
        $ref = new \ReflectionObject($request);
        if ($ref->hasProperty('validator')) {
            $prop = $ref->getProperty('validator');
            $prop->setAccessible(true);
            $prop->setValue($request, $validatorStub);
        }

        $controller = new OverviewCourseController();
        $response = $controller->update($request, $course->id);

        $responseData = $this->resolveResponseData($response, $request);

        // Assert response status/message
        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Course summary updated successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];
        $this->assertIsArray($data);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals($course->id, $data['id']);

        // reload course from DB and assert fields updated
        $updated = Course::find($course->id);
        $this->assertNotNull($updated);
        $this->assertEquals($newTitle, $updated->title);
        $this->assertEquals($newLevel, $updated->level);
        $this->assertEquals($newStatus, $updated->status);
        // the controller uses a WysiwygTrait to handle detail updates; expect the saved detail to contain provided content
        $this->assertStringContainsString('Updated detail content', (string)$updated->detail);
    }
}
