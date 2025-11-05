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

class AttributeCourseIndexTest extends TestCase
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

    /**
     * Walk nested arrays to find the first numerically indexed array of item-rows.
     */
    private function extractItems(array $responseData)
    {
        $queue = [$responseData];
        while (!empty($queue)) {
            $node = array_shift($queue);
            if (!is_array($node)) {
                continue;
            }

            if ($this->isNumericArray($node)) {
                if (isset($node[0]) && (is_array($node[0]) || is_object($node[0]))) {
                    return array_map(function ($it) {
                        return is_object($it) ? (array) $it : $it;
                    }, $node);
                }
            }

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

    public function test_index_returns_grouped_attributes_by_type()
    {
        // prepare a course id and sample attributes
        $courseId = Str::uuid()->toString();

        // Insert minimal course row while disabling FK checks to avoid dependent table requirements
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
                'title' => 'Test Course',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // create attributes across types
            $descriptionContent = ['This is description 1', 'This is description 2'];
            $prereqContent = ['Prereq A', 'Prereq B'];
            $benefitContent = ['Benefit X'];

            CourseAttribute::create([
                'id' => Str::uuid()->toString(),
                'course_id' => $courseId,
                'type' => 'description',
                'content' => $descriptionContent,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            CourseAttribute::create([
                'id' => Str::uuid()->toString(),
                'course_id' => $courseId,
                'type' => 'prerequisite',
                'content' => $prereqContent,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            CourseAttribute::create([
                'id' => Str::uuid()->toString(),
                'course_id' => $courseId,
                'type' => 'benefit',
                'content' => $benefitContent,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } finally {
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON;');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        }

        $controller = new AttributeCourseController();
        $request = new Request();
        $response = $controller->index($courseId);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);

        // Accept either boolean true or string 'success' for status
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Data berhasil diambil.', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        // Expect keys for each type created
        $this->assertTrue(
            isset($data['description']) || array_key_exists('description', $data),
            'Expected description group present'
        );
        $this->assertTrue(
            isset($data['prerequisite']) || array_key_exists('prerequisite', $data),
            'Expected prerequisite group present'
        );
        $this->assertTrue(
            isset($data['benefit']) || array_key_exists('benefit', $data),
            'Expected benefit group present'
        );

        // Extract items for each group and ensure contents exist
        $descItems = $this->extractItems(is_array($data['description']) ? $data['description'] : (array) $data['description']);
        $this->assertNotEmpty($descItems, 'Description group should contain items');

        $prereqItems = $this->extractItems(is_array($data['prerequisite']) ? $data['prerequisite'] : (array) $data['prerequisite']);
        $this->assertNotEmpty($prereqItems, 'Prerequisite group should contain items');

        $benefitItems = $this->extractItems(is_array($data['benefit']) ? $data['benefit'] : (array) $data['benefit']);
        $this->assertNotEmpty($benefitItems, 'Benefit group should contain items');

        // verify at least one known content element appears in each group's serialized content
        $foundDesc = false;
        foreach ($descItems as $it) {
            if (isset($it['content']) && (is_array($it['content']) || is_string($it['content']))) {
                $serialized = is_string($it['content']) ? $it['content'] : json_encode($it['content']);
                if (strpos($serialized, 'This is description 1') !== false) {
                    $foundDesc = true;
                    break;
                }
            }
        }
        $this->assertTrue($foundDesc, 'Expected description content to be present in response');

        $foundPrereq = false;
        foreach ($prereqItems as $it) {
            if (isset($it['content']) && (is_array($it['content']) || is_string($it['content']))) {
                $serialized = is_string($it['content']) ? $it['content'] : json_encode($it['content']);
                if (strpos($serialized, 'Prereq A') !== false) {
                    $foundPrereq = true;
                    break;
                }
            }
        }
        $this->assertTrue($foundPrereq, 'Expected prerequisite content to be present in response');

        $foundBenefit = false;
        foreach ($benefitItems as $it) {
            if (isset($it['content']) && (is_array($it['content']) || is_string($it['content']))) {
                $serialized = is_string($it['content']) ? $it['content'] : json_encode($it['content']);
                if (strpos($serialized, 'Benefit X') !== false) {
                    $foundBenefit = true;
                    break;
                }
            }
        }
        $this->assertTrue($foundBenefit, 'Expected benefit content to be present in response');
    }

    public function test_index_returns_400_when_no_id_provided()
    {
        $controller = new AttributeCourseController();
        $response = $controller->index(null);

        // Should return JsonResponse with error message and 400 status
        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = $response->getData(true);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Course ID is required', $data['error']);
    }
}
