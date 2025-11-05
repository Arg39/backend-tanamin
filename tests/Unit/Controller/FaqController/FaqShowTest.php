<?php

namespace Tests\Unit\Controller\FaqController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Faq;
use App\Http\Controllers\Api\FaqController;

class FaqShowTest extends TestCase
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

    public function test_show_returns_faq_when_exists()
    {
        $faq = Faq::create([
            'id' => Str::uuid()->toString(),
            'question' => 'Test question show',
            'answer' => 'Test answer show',
        ]);

        $controller = new FaqController();
        $request = new Request();
        $response = $controller->show($faq->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);

        // Accept either boolean true or string 'success' for status
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true for existing FAQ');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('FAQ retrieved successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        // data may be object or array; normalize
        $it = is_object($data) ? (array) $data : $data;
        $this->assertIsArray($it, 'Expected data to be an array or array-like structure');
        $this->assertArrayHasKey('question', $it);
        $this->assertArrayHasKey('answer', $it);
        $this->assertEquals('Test question show', $it['question']);
        $this->assertEquals('Test answer show', $it['answer']);
    }

    public function test_show_returns_not_found_for_missing_faq()
    {
        // ensure this id does not exist
        $missingId = Str::uuid()->toString();
        Faq::where('id', $missingId)->delete();

        $controller = new FaqController();
        $request = new Request();
        $response = $controller->show($missingId);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);

        // Accept either boolean false or a non-success status string
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status'], 'Expected status to be false for missing FAQ');
        } else {
            $this->assertNotEquals('success', $responseData['status'], 'Expected non-success status for missing FAQ');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('FAQ not found', $responseData['message']);
    }
}
