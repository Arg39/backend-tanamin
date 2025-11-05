<?php

namespace Tests\Unit\Controller\FaqController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Faq;
use App\Http\Controllers\Api\FaqController;

class FaqDestroyTest extends TestCase
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

    public function test_destroy_deletes_faq_successfully()
    {
        $faq = Faq::create([
            'id' => Str::uuid()->toString(),
            'question' => 'To be deleted Q',
            'answer' => 'To be deleted A',
        ]);

        $controller = new FaqController();
        $response = $controller->destroy($faq->id);
        $responseData = $this->resolveResponseData($response, request());

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('FAQ deleted successfully', $responseData['message']);

        // Ensure it was removed from DB
        $this->assertNull(Faq::find($faq->id), 'Expected FAQ to be deleted from database');
    }

    public function test_destroy_returns_not_found_for_invalid_id()
    {
        $invalidId = Str::uuid()->toString();

        $controller = new FaqController();
        $response = $controller->destroy($invalidId);
        $responseData = $this->resolveResponseData($response, request());

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status'], 'Expected status to be false for not found');
        } else {
            $this->assertNotEquals('success', $responseData['status'], 'Expected non-success status for not found');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('FAQ not found', $responseData['message']);
    }
}
