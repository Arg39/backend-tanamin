<?php

namespace Tests\Unit\Controller\FaqController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Faq;
use App\Http\Controllers\Api\FaqController;

class FaqUpdateTest extends TestCase
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
     * Breadth-first search to find first node (array/object) that contains all specified keys.
     */
    private function findNodeWithKeys($node, array $keys)
    {
        $queue = [is_object($node) ? (array) $node : $node];
        while (!empty($queue)) {
            $current = array_shift($queue);
            if (!is_array($current)) {
                continue;
            }
            $hasAll = true;
            foreach ($keys as $k) {
                if (!array_key_exists($k, $current)) {
                    $hasAll = false;
                    break;
                }
            }
            if ($hasAll) {
                return $current;
            }
            foreach ($current as $child) {
                if (is_array($child) || is_object($child)) {
                    $queue[] = is_object($child) ? (array) $child : $child;
                }
            }
        }
        return null;
    }

    public function test_update_updates_faq_successfully()
    {
        $faq = Faq::create([
            'id' => Str::uuid()->toString(),
            'question' => 'Original Q',
            'answer' => 'Original A',
        ]);

        $controller = new FaqController();
        $request = new Request([
            'question' => 'Updated Question',
            'answer' => 'Updated Answer',
        ]);

        $response = $controller->update($request, $faq->id);
        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('FAQ updated successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $dataNode = $this->findNodeWithKeys($responseData['data'], ['question', 'answer']);
        $this->assertNotNull($dataNode, 'Expected response data to contain question and answer fields');

        $this->assertEquals('Updated Question', $dataNode['question']);
        $this->assertEquals('Updated Answer', $dataNode['answer']);
    }

    public function test_update_returns_not_found_for_invalid_id()
    {
        // ensure no FAQ exists with this id
        $invalidId = Str::uuid()->toString();

        $controller = new FaqController();
        $request = new Request([
            'question' => 'Does not matter',
            'answer' => 'Does not matter',
        ]);

        $response = $controller->update($request, $invalidId);
        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status'], 'Expected status to be false for not found');
        } else {
            $this->assertNotEquals('success', $responseData['status'], 'Expected non-success status for not found');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('FAQ not found', $responseData['message']);
    }

    public function test_update_validation_fails_when_missing_fields()
    {
        $faq = Faq::create([
            'id' => Str::uuid()->toString(),
            'question' => 'Orig Q',
            'answer' => 'Orig A',
        ]);

        $controller = new FaqController();
        // missing 'answer' intentionally
        $request = new Request([
            'question' => 'Only Question Provided',
        ]);

        $response = $controller->update($request, $faq->id);
        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status'], 'Expected status to be false on validation failure');
        } else {
            $this->assertNotEquals('success', $responseData['status'], 'Expected non-success status on validation failure');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertIsString($responseData['message']);
        $this->assertStringContainsStringIgnoringCase('required', $responseData['message']);
    }
}
