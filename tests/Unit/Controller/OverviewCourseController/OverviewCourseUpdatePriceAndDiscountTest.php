<?php

namespace Tests\Unit\Controller\OverviewCourseController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Models\Course;
use App\Models\Category;
use App\Models\User;
use App\Http\Controllers\Api\Course\OverviewCourseController;
use App\Http\Requests\UpdateCoursePriceRequest;

class OverviewCourseUpdatePriceAndDiscountTest extends TestCase
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
     * Inject a simple validator stub into a FormRequest so ->validated() returns provided data.
     */
    private function injectValidatedDataIntoFormRequest($formRequest, array $data)
    {
        $validatorStub = new class($data) {
            private $data;
            public function __construct($data)
            {
                $this->data = $data;
            }
            public function validated()
            {
                return $this->data;
            }
        };

        $ref = new \ReflectionObject($formRequest);
        if ($ref->hasProperty('validator')) {
            $prop = $ref->getProperty('validator');
            $prop->setAccessible(true);
            $prop->setValue($formRequest, $validatorStub);
        }
    }

    public function test_update_sets_price_and_returns_null_discount()
    {
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Price Category',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'Price',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Price Course',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $request = new UpdateCoursePriceRequest();
        $payload = ['price' => 150000];
        $request->merge($payload);
        $this->injectValidatedDataIntoFormRequest($request, $payload);

        $controller = new OverviewCourseController();
        $response = $controller->updatePriceAndDiscount($request, $course->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertTrue($responseData['status']);
        } else {
            $this->assertEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Course price and discount updated successfully', $responseData['message']);

        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('discount', $responseData['data']);
        $this->assertNull($responseData['data']['discount']);

        $fresh = Course::find($course->id);
        $this->assertEquals(150000, $fresh->price);
    }

    public function test_cannot_add_discount_when_price_not_set_and_price_not_provided()
    {
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'NoPrice Category',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'NoPrice',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'No Price Course',
            'price' => null,
            'is_discount_active' => false,
        ]);

        $request = new UpdateCoursePriceRequest();
        $payload = [
            'discount_type' => 'percent',
            'discount_value' => 10,
            'is_discount_active' => true,
        ];
        $request->merge($payload);
        $this->injectValidatedDataIntoFormRequest($request, $payload);

        $controller = new OverviewCourseController();
        $response = $controller->updatePriceAndDiscount($request, $course->id);

        $responseData = $this->resolveResponseData($response, $request);

        $this->assertArrayHasKey('status', $responseData);
        if (is_bool($responseData['status'])) {
            $this->assertFalse($responseData['status']);
        } else {
            $this->assertNotEquals('success', $responseData['status']);
        }

        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('You need to set the course price first before you can add a discount.', $responseData['message']);

        $fresh = Course::find($course->id);
        $this->assertNull($fresh->price);
        $this->assertFalse((bool)$fresh->is_discount_active);
    }

    public function test_update_discount_fields_and_return_formatted_discount_for_percent_and_nominal()
    {
        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Discount Update Category',
            'image' => null,
        ]);

        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_' . Str::random(6),
            'first_name' => 'Inst',
            'last_name' => 'DiscountUpd',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        // start with a course that already has a price
        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Discountable Course',
            'price' => 100000,
            'is_discount_active' => false,
        ]);

        $controller = new OverviewCourseController();
        $requestPercent = new UpdateCoursePriceRequest();
        $payloadPercent = [
            'discount_type' => 'percent',
            'discount_value' => 15,
            'is_discount_active' => true,
        ];
        $requestPercent->merge($payloadPercent);
        $this->injectValidatedDataIntoFormRequest($requestPercent, $payloadPercent);

        $responsePercent = $controller->updatePriceAndDiscount($requestPercent, $course->id);
        $responseDataPercent = $this->resolveResponseData($responsePercent, $requestPercent);

        $this->assertArrayHasKey('status', $responseDataPercent);
        if (is_bool($responseDataPercent['status'])) {
            $this->assertTrue($responseDataPercent['status']);
        } else {
            $this->assertEquals('success', $responseDataPercent['status']);
        }

        $this->assertArrayHasKey('data', $responseDataPercent);
        $this->assertEquals('15 %', $responseDataPercent['data']['discount']);

        $fresh = Course::find($course->id);
        $this->assertEquals('percent', $fresh->discount_type);
        $this->assertEquals(15, $fresh->discount_value);
        $this->assertTrue((bool)$fresh->is_discount_active);

        // Now update to nominal discount
        $requestNominal = new UpdateCoursePriceRequest();
        $payloadNominal = [
            'discount_type' => 'nominal',
            'discount_value' => 50000,
            'is_discount_active' => true,
        ];
        $requestNominal->merge($payloadNominal);
        $this->injectValidatedDataIntoFormRequest($requestNominal, $payloadNominal);

        $responseNominal = $controller->updatePriceAndDiscount($requestNominal, $course->id);
        $responseDataNominal = $this->resolveResponseData($responseNominal, $requestNominal);

        $this->assertArrayHasKey('status', $responseDataNominal);
        if (is_bool($responseDataNominal['status'])) {
            $this->assertTrue($responseDataNominal['status']);
        } else {
            $this->assertEquals('success', $responseDataNominal['status']);
        }

        $this->assertArrayHasKey('data', $responseDataNominal);
        $this->assertEquals('Rp. ' . number_format(50000, 0, ',', '.'), $responseDataNominal['data']['discount']);

        $fresh2 = Course::find($course->id);
        $this->assertEquals('nominal', $fresh2->discount_type);
        $this->assertEquals(50000, $fresh2->discount_value);
        $this->assertTrue((bool)$fresh2->is_discount_active);
    }
}
