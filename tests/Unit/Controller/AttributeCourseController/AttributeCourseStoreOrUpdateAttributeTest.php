<?php

namespace Tests\Unit\Controller\AttributeCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\CourseAttribute;
use App\Http\Controllers\Api\Course\AttributeCourseController;

class AttributeCourseStoreOrUpdateAttributeTest extends TestCase
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

    public function test_store_or_update_creates_new_attributes()
    {
        $courseId = Str::uuid()->toString();

        // disable FK checks for insertion of minimal course
        $driver = DB::getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        try {
            DB::table('courses')->insert([
                'id' => $courseId,
                'category_id' => Str::uuid()->toString(),
                'instructor_id' => Str::uuid()->toString(),
                'title' => 'StoreOrUpdate Test Course',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $descriptions = ['Desc 1', 'Desc 2'];
            $prerequisites = ['Pre A'];
            $benefits = ['Ben X', 'Ben Y'];

            $controller = new AttributeCourseController();
            $request = new Request([
                'descriptions' => $descriptions,
                'prerequisites' => $prerequisites,
                'benefits' => $benefits,
            ]);

            $response = $controller->storeOrUpdateAttribute($request, $courseId);
            $responseData = $this->resolveResponseData($response, $request);

            // status/message assertions
            $this->assertArrayHasKey('status', $responseData);
            if (is_bool($responseData['status'])) {
                $this->assertTrue($responseData['status']);
            } else {
                $this->assertEquals('success', $responseData['status']);
            }
            $this->assertArrayHasKey('message', $responseData);
            $this->assertEquals('Atribut kursus berhasil disimpan/diperbarui.', $responseData['message']);

            // data structure assertions
            $this->assertArrayHasKey('data', $responseData);
            $data = $responseData['data'];
            $this->assertTrue(isset($data['description']) || array_key_exists('description', $data));
            $this->assertTrue(isset($data['prerequisite']) || array_key_exists('prerequisite', $data));
            $this->assertTrue(isset($data['benefit']) || array_key_exists('benefit', $data));

            // verify DB entries
            $attrs = CourseAttribute::where('course_id', $courseId)->get();
            $this->assertCount(3, $attrs, 'Expected three CourseAttribute rows to be created');

            $byType = $attrs->keyBy('type');
            $this->assertEquals($descriptions, $byType['description']->content);
            $this->assertEquals($prerequisites, $byType['prerequisite']->content);
            $this->assertEquals($benefits, $byType['benefit']->content);
        } finally {
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON;');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        }
    }

    public function test_store_or_update_updates_existing_attribute()
    {
        $courseId = Str::uuid()->toString();

        // disable FK checks for insertion of minimal course
        $driver = DB::getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        try {
            DB::table('courses')->insert([
                'id' => $courseId,
                'category_id' => Str::uuid()->toString(),
                'instructor_id' => Str::uuid()->toString(),
                'title' => 'UpdateAttribute Test Course',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $initial = ['Initial desc'];
            $attr = CourseAttribute::create([
                'id' => Str::uuid()->toString(),
                'course_id' => $courseId,
                'type' => 'description',
                'content' => $initial,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $newDescriptions = ['Updated desc 1', 'Updated desc 2'];

            $controller = new AttributeCourseController();
            $request = new Request([
                'descriptions' => $newDescriptions,
            ]);

            $response = $controller->storeOrUpdateAttribute($request, $courseId);
            $responseData = $this->resolveResponseData($response, $request);

            $this->assertArrayHasKey('status', $responseData);
            if (is_bool($responseData['status'])) {
                $this->assertTrue($responseData['status']);
            } else {
                $this->assertEquals('success', $responseData['status']);
            }
            $this->assertArrayHasKey('message', $responseData);
            $this->assertEquals('Atribut kursus berhasil disimpan/diperbarui.', $responseData['message']);

            // reload and assert update
            $updated = CourseAttribute::where('course_id', $courseId)->where('type', 'description')->first();
            $this->assertNotNull($updated);
            $this->assertEquals($attr->id, $updated->id, 'Expected same id for updated attribute');
            $this->assertEquals($newDescriptions, $updated->content, 'Expected content to be updated');
        } finally {
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON;');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        }
    }
}
