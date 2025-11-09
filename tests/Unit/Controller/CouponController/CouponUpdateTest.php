<?php

namespace Tests\Unit\Controller\CouponController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Coupon;
use App\Http\Controllers\Api\CouponController;

class CouponUpdateTest extends TestCase
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

    public function test_update_updates_coupon_successfully()
    {
        $coupon = Coupon::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Original Title',
            'code' => 'CPN_ORIG_' . Str::random(6),
            'type' => 'percent',
            'value' => 10,
            'start_at' => Carbon::now()->subDay(),
            'end_at' => Carbon::now()->addDay(),
            'is_active' => true,
            'max_usage' => null,
            'used_count' => 0,
        ]);

        $payload = [
            'title' => 'Updated Title',
            'code' => 'CPN_UPDATED_' . Str::random(6),
            'value' => 25,
            'is_active' => false,
        ];

        $controller = new CouponController();
        $request = new Request($payload);
        $response = $controller->update($coupon->id, $request);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status to be true on successful update');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string to be "success" on update');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Coupon updated', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $dataNode = $this->findNodeWithKeys($responseData['data'], ['code', 'title']);
        $this->assertNotNull($dataNode, 'Expected response data to contain code and title fields');

        $this->assertEquals($payload['code'], $dataNode['code']);
        $this->assertEquals($payload['title'], $dataNode['title']);

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'code' => $payload['code'],
            'title' => $payload['title'],
            'value' => $payload['value'],
            'is_active' => 0, // false stored as 0
        ]);
    }

    public function test_update_returns_not_found_for_invalid_id()
    {
        $invalidId = Str::uuid()->toString();

        $controller = new CouponController();
        $request = new Request([
            'title' => 'Does not matter',
        ]);

        $response = $controller->update($invalidId, $request);
        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status'], 'Expected status to be false for not found');
        } else {
            $this->assertNotEquals('success', $responseData['status'], 'Expected non-success status for not found');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Coupon not found', $responseData['message']);
    }

    public function test_update_validation_fails_when_code_not_unique()
    {
        $couponA = Coupon::create([
            'id' => Str::uuid()->toString(),
            'title' => 'A',
            'code' => 'CPN_A_' . Str::random(6),
            'type' => 'percent',
            'value' => 5,
            'start_at' => Carbon::now()->subDay(),
            'end_at' => Carbon::now()->addDay(),
            'is_active' => true,
            'max_usage' => null,
            'used_count' => 0,
        ]);

        $couponB = Coupon::create([
            'id' => Str::uuid()->toString(),
            'title' => 'B',
            'code' => 'CPN_B_' . Str::random(6),
            'type' => 'percent',
            'value' => 10,
            'start_at' => Carbon::now()->subDay(),
            'end_at' => Carbon::now()->addDay(),
            'is_active' => true,
            'max_usage' => null,
            'used_count' => 0,
        ]);

        $controller = new CouponController();
        $request = new Request([
            // attempt to set couponA code to couponB's code -> should fail unique validation
            'code' => $couponB->code,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->update($couponA->id, $request);
    }

    public function test_partial_update_allows_updating_single_field()
    {
        $coupon = Coupon::create([
            'id' => Str::uuid()->toString(),
            'title' => 'Orig Title',
            'code' => 'CPN_PARTIAL_' . Str::random(6),
            'type' => 'nominal',
            'value' => 100,
            'start_at' => Carbon::now()->subDay(),
            'end_at' => Carbon::now()->addDay(),
            'is_active' => true,
            'max_usage' => null,
            'used_count' => 0,
        ]);

        $controller = new CouponController();
        $request = new Request([
            'title' => 'Only Title Changed',
        ]);

        $response = $controller->update($coupon->id, $request);
        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Coupon updated', $responseData['message']);

        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'title' => 'Only Title Changed',
            'code' => $coupon->code, // unchanged
        ]);
    }
}