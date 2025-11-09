<?php

namespace Tests\Unit\Controller\CouponController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Coupon;
use App\Http\Controllers\Api\CouponController;

class CouponDestroyTest extends TestCase
{
    use DatabaseTransactions;

    // ...existing code...

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

    public function test_destroy_deletes_existing_coupon()
    {
        $coupon = Coupon::create([
            'id' => Str::uuid()->toString(),
            'title' => 'To Be Deleted',
            'code' => 'DEL_' . Str::random(8),
            'type' => 'percent',
            'value' => 10,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'is_active' => true,
            'max_usage' => null,
            'used_count' => 0,
        ]);

        $controller = new CouponController();
        $request = new Request();
        $response = $controller->destroy($coupon->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status'], 'Expected status true after successful delete');
        } else {
            $this->assertEquals('success', $responseData['status'], 'Expected status string "success" after delete');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Coupon deleted', $responseData['message']);

        // Ensure the coupon can no longer be found via Eloquent (covers soft-delete and hard-delete)
        $this->assertNull(Coupon::find($coupon->id), 'Expected coupon to be not found after deletion');
    }

    public function test_destroy_returns_not_found_for_invalid_id()
    {
        $invalidId = Str::uuid()->toString();

        $controller = new CouponController();
        $request = new Request();
        $response = $controller->destroy($invalidId);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status'], 'Expected status false for not found');
        } else {
            $this->assertNotEquals('success', $responseData['status'], 'Expected non-success status for not found');
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Coupon not found', $responseData['message']);
    }
}