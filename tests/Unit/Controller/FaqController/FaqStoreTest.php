<?php

namespace Tests\Unit\Controller\FaqController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Faq;
use App\Http\Controllers\Api\FaqController;

class FaqStoreTest extends TestCase
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

    public function test_store_creates_faq_with_valid_payload()
    {
        $payload = [
            'question' => 'How to plant tomatoes?',
            'answer' => 'Prepare soil, plant seeds, water regularly.',
        ];

        $controller = new FaqController();
        $request = new Request($payload);
        $response = $controller->store($request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);

        // Accept either boolean true or string 'success' for status
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('FAQ created successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        // resource may return array or object; normalize
        $it = is_object($data) ? (array) $data : $data;
        $this->assertIsArray($it);
        $this->assertArrayHasKey('question', $it);
        $this->assertArrayHasKey('answer', $it);
        $this->assertEquals($payload['question'], $it['question']);
        $this->assertEquals($payload['answer'], $it['answer']);

        // Verify persisted in DB (use actual table name 'faq' per migration/model)
        $this->assertDatabaseHas('faq', [
            'question' => $payload['question'],
            'answer' => $payload['answer'],
        ]);
    }

    public function test_store_returns_validation_error_when_missing_fields()
    {
        $controller = new FaqController();
        $request = new Request([]); // missing required fields
        $response = $controller->store($request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);

        // Expect failure: boolean false or not 'success'
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status'], 'Expected status to be false on validation error');
        } else {
            $this->assertNotEquals('success', $responseData['status'], 'Status should not be "success" on validation error');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertNotEmpty($responseData['message'], 'Expected an error message for validation failure');

        // Controller may omit 'data' or set it to null on validation error; accept both.
        $this->assertTrue(!array_key_exists('data', $responseData) || is_null($responseData['data']), 'Expected data to be missing or null on validation failure');
    }
}
