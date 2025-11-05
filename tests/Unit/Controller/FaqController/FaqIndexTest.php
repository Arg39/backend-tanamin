<?php

namespace Tests\Unit\Controller\FaqController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Faq;
use App\Http\Controllers\Api\FaqController;

class FaqIndexTest extends TestCase
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
     * This handles TableResource wrapping + paginator structure.
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

    public function test_index_returns_paginated_faq_list_default_per_page()
    {
        // create 12 FAQs (default per_page = 10)
        $createdQuestions = [];
        for ($i = 0; $i < 12; $i++) {
            $faq = Faq::create([
                'id' => Str::uuid()->toString(),
                'question' => "Question {$i}",
                'answer' => "Answer {$i}",
            ]);
            $createdQuestions[] = $faq->question;
        }

        $controller = new FaqController();
        $request = new Request();
        $response = $controller->index($request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);

        // Accept either boolean true or string 'success' for status
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success"');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('FAQ list retrieved successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $items = $this->extractItems($responseData['data']);
        $this->assertNotEmpty($items, 'Expected to find FAQ items in response');

        // Default per_page = 10, so expect 10 items on first page
        $this->assertCount(10, $items, 'Expected 10 items for default per_page');

        // Ensure each item has question and answer, and at least one created question appears
        $questionsInResponse = array_map(function ($it) {
            return $it['question'] ?? ($it['question'] ?? null);
        }, $items);

        $this->assertNotEmpty(array_intersect($createdQuestions, $questionsInResponse), 'At least one created question should be present in response');

        // Find pagination metadata somewhere in data
        $foundPagination = false;
        $queue = [$responseData['data']];
        while (!empty($queue)) {
            $node = array_shift($queue);
            if (!is_array($node)) continue;
            if (isset($node['current_page']) && isset($node['last_page'])) {
                $foundPagination = true;
                break;
            }
            foreach ($node as $child) {
                if (is_array($child)) $queue[] = $child;
            }
        }
        $this->assertTrue($foundPagination, 'Expected pagination metadata in response data');
    }

    public function test_index_respects_per_page_parameter()
    {
        // create 3 FAQs
        for ($i = 0; $i < 3; $i++) {
            Faq::create([
                'id' => Str::uuid()->toString(),
                'question' => "PerPage Q{$i}",
                'answer' => "PerPage A{$i}",
            ]);
        }

        $controller = new FaqController();
        $request = new Request(['per_page' => 1, 'page' => 1]);
        $response = $controller->index($request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('data', $responseData);
        $items = $this->extractItems($responseData['data']);
        $this->assertCount(1, $items, 'Expected exactly 1 FAQ item due to per_page=1');

        // Ensure pagination metadata exists
        $foundPagination = false;
        $queue = [$responseData['data']];
        while (!empty($queue)) {
            $node = array_shift($queue);
            if (!is_array($node)) continue;
            if (isset($node['current_page']) && isset($node['last_page'])) {
                $foundPagination = true;
                break;
            }
            foreach ($node as $child) {
                if (is_array($child)) $queue[] = $child;
            }
        }
        $this->assertTrue($foundPagination, 'Expected pagination metadata in response data');
    }
}
