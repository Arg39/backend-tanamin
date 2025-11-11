<?php

namespace Tests\Unit\Controller\CertificateController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use App\Models\Certificate;
use App\Http\Controllers\Api\Course\CertificateController;

class CertificateVerifyCertificateCodeTest extends TestCase
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

    public function test_returns_error_when_certificate_code_not_found()
    {
        $controller = new CertificateController();
        $response = $controller->verifyCertificateCode('NON-EXISTENT-CODE');

        $data = $this->resolveResponseData($response, new Request());

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertFalse($data['status']);
        } else {
            $this->assertNotEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Kode sertifikat tidak ditemukan.', $data['message']);
    }

    public function test_returns_certificate_data_when_code_exists()
    {
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_verify_' . Str::random(6),
            'first_name' => 'Instructor',
            'last_name' => 'Verify',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Verify Category',
            'image' => null,
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Verify Course',
            'price' => 0,
            'is_discount_active' => false,
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_verify_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Verify',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $certificateCode = 'TC-VERIFY-' . Str::upper(Str::random(6));

        $issuedAt = now();
        $certificate = Certificate::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'certificate_code' => $certificateCode,
            'issued_at' => $issuedAt,
        ]);

        $controller = new CertificateController();
        $response = $controller->verifyCertificateCode($certificateCode);

        $data = $this->resolveResponseData($response, new Request());

        $this->assertArrayHasKey('status', $data);
        if (is_bool($data['status'])) {
            $this->assertTrue($data['status']);
        } else {
            $this->assertEquals('success', $data['status']);
        }

        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Kode sertifikat valid.', $data['message']);

        $this->assertArrayHasKey('data', $data);
        $payload = $data['data'];

        $this->assertArrayHasKey('certificate_code', $payload);
        $this->assertEquals($certificateCode, $payload['certificate_code']);

        $this->assertArrayHasKey('user_fullname', $payload);
        $this->assertEquals("{$student->first_name} {$student->last_name}", $payload['user_fullname']);

        $this->assertArrayHasKey('course_title', $payload);
        $this->assertEquals($course->title, $payload['course_title']);

        $this->assertArrayHasKey('issued_at', $payload);
        $expectedIssuedAt = Carbon::parse($issuedAt)->locale('id')->translatedFormat('d F Y');
        $this->assertEquals($expectedIssuedAt, $payload['issued_at']);
    }
}
