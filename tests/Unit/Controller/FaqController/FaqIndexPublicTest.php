<?php

namespace Tests\Unit\Controller\FaqController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Faq;
use App\Http\Controllers\Api\FaqController;

class FaqIndexPublicTest extends TestCase
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

    public function test_index_public_returns_empty_array_when_no_faqs()
    {
        // Ensure no Faqs exist for this test
        Faq::query()->delete();

        $controller = new FaqController();
        $request = new Request();
        $response = $controller->indexPublic();

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);

        // Accept either boolean true or string 'success' for status
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('All FAQs retrieved successfully', $responseData['message']);

        // data may be empty array
        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);
        $this->assertEmpty($responseData['data'], 'Expected data to be empty when no FAQs exist');
    }

    public function test_index_public_returns_faq_list_with_question_and_answer()
    {
        $createdQuestions = [];
        $createdAnswers = [];
        for ($i = 0; $i < 3; $i++) {
            $faq = Faq::create([
                'id' => Str::uuid()->toString(),
                'question' => "Public Q{$i}",
                'answer' => "Public A{$i}",
            ]);
            $createdQuestions[] = $faq->question;
            $createdAnswers[] = $faq->answer;
        }

        $controller = new FaqController();
        $request = new Request();
        $response = $controller->indexPublic();

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);

        // Accept either boolean true or string 'success' for status
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('All FAQs retrieved successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $data = $responseData['data'];

        // data should be an array of items (questions & answers)
        $this->assertIsArray($data);

        // Collect questions and answers returned
        $questionsInResponse = [];
        $answersInResponse = [];
        foreach ($data as $item) {
            // item might be array or object, normalize to array
            $it = is_object($item) ? (array) $item : $item;
            $this->assertIsArray($it);
            $this->assertArrayHasKey('question', $it);
            $this->assertArrayHasKey('answer', $it);
            $questionsInResponse[] = $it['question'];
            $answersInResponse[] = $it['answer'];
        }

        // ensure each created entry appears in the response
        foreach ($createdQuestions as $cq) {
            $this->assertTrue(in_array($cq, $questionsInResponse), "Created question '{$cq}' should be present in response");
        }
        foreach ($createdAnswers as $ca) {
            $this->assertTrue(in_array($ca, $answersInResponse), "Created answer '{$ca}' should be present in response");
        }
    }
}
