<?php

namespace Tests\Unit\Controller\CompanyContactUsController;

use App\Http\Controllers\Api\Company\CompanyContactUsController;
use App\Models\ContactUsMessage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Tests\TestCase;

class CompanyContactUsIndexTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_returns_paginated_contact_us_messages(): void
    {
        // Ensure no other messages interfere
        ContactUsMessage::query()->delete();

        // Create sample messages
        $messagesData = [
            [
                'name' => 'Alice',
                'email' => 'alice@example.com',
                'subject' => 'Subject A',
                'message' => 'Message A',
            ],
            [
                'name' => 'Bob',
                'email' => 'bob@example.com',
                'subject' => 'Subject B',
                'message' => 'Message B',
            ],
            [
                'name' => 'Carol',
                'email' => 'carol@example.com',
                'subject' => 'Subject C',
                'message' => 'Message C',
            ],
        ];

        // Use deterministic timestamps so ordering by created_at is predictable
        $base = Carbon::now();

        foreach ($messagesData as $i => $data) {
            // include id and explicit timestamps to guarantee order
            ContactUsMessage::create(array_merge([
                'id' => Str::uuid()->toString(),
                'created_at' => $base->copy()->addSeconds($i),
                'updated_at' => $base->copy()->addSeconds($i),
            ], $data));
        }

        $perPage = 10;
        $request = new Request(['per_page' => $perPage]);

        $controller = new CompanyContactUsController();
        $result = $controller->index($request);

        // Normalize response into array
        $responseData = null;
        if (is_object($result) && method_exists($result, 'toResponse')) {
            $httpResponse = $result->toResponse(request());
            $this->assertEquals(200, $httpResponse->getStatusCode());
            $responseData = $httpResponse->getData(true);
        } elseif (is_array($result)) {
            $responseData = $result;
        } else {
            $encoded = json_encode($result);
            $responseData = json_decode($encoded, true) ?? [];
        }

        // Assert message
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Contact us messages retrieved successfully.', $responseData['message']);

        // Assert data exists
        $this->assertArrayHasKey('data', $responseData);
        $this->assertIsArray($responseData['data']);

        // Extract items from possible paginator/resource structure
        $tableData = $responseData['data'];
        $items = [];

        // 1) paginator under 'data' key
        if (isset($tableData['data']) && is_array($tableData['data'])) {
            $items = $tableData['data'];
        }
        // 2) some resources use 'items' key with pagination metadata
        elseif (isset($tableData['items']) && is_array($tableData['items'])) {
            $items = $tableData['items'];
        }
        // 3) direct sequential array
        elseif (is_array($tableData) && array_values($tableData) === $tableData) {
            $items = $tableData;
        }
        // 4) associative array but numeric keys (treat as list)
        elseif (is_array($tableData) && $this->isAssocArrayOfItems($tableData)) {
            $items = array_values($tableData);
        } else {
            $items = [];
        }

        // Ensure at least the created messages are present (respecting per_page)
        $expectedCount = min(count($messagesData), $perPage);
        $this->assertGreaterThanOrEqual($expectedCount, count($items));

        // Normalize all items to arrays for easy inspection
        $normalizedItems = array_map(function ($it) {
            if (is_object($it)) {
                return json_decode(json_encode($it), true);
            }
            return $it;
        }, $items);

        // Assert each returned item has required fields
        foreach ($normalizedItems as $item) {
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('email', $item);
            $this->assertArrayHasKey('subject', $item);
            $this->assertArrayHasKey('message', $item);
        }

        // Verify that all created messages are present in the returned items (order-agnostic)
        $foundNames = array_map(function ($it) {
            return $it['name'] ?? null;
        }, $normalizedItems);

        $foundEmails = array_map(function ($it) {
            return $it['email'] ?? null;
        }, $normalizedItems);

        $foundSubjects = array_map(function ($it) {
            return $it['subject'] ?? null;
        }, $normalizedItems);

        $foundMessages = array_map(function ($it) {
            return $it['message'] ?? null;
        }, $normalizedItems);

        foreach ($messagesData as $expected) {
            $this->assertContains($expected['name'], $foundNames, 'Expected name not found in returned items');
            $this->assertContains($expected['email'], $foundEmails, 'Expected email not found in returned items');
            $this->assertContains($expected['subject'], $foundSubjects, 'Expected subject not found in returned items');
            $this->assertContains($expected['message'], $foundMessages, 'Expected message body not found in returned items');
        }
    }

    /**
     * Helper to detect associative array that contains items (not metadata).
     */
    private function isAssocArrayOfItems(array $arr): bool
    {
        // Return true when all keys are integers (i.e. list-like but possibly associative)
        $keys = array_keys($arr);
        foreach ($keys as $k) {
            if (!is_int($k)) {
                return false;
            }
        }
        return true;
    }
}
