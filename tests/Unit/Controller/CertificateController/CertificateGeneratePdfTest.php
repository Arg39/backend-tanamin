<?php

namespace Tests\Unit\Controller\CertificateController;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Mockery;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use App\Models\Certificate;
use App\Http\Controllers\Api\Course\CertificateController;

class CertificateGeneratePdfTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test that generatePdf returns 404 when certificate not found.
     */
    public function test_returns_404_when_certificate_not_found()
    {
        $controller = new CertificateController();
        $response = $controller->generatePdf('NON-EXISTENT-CODE');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('Sertifikat tidak ditemukan', $response->getContent());
    }

    /**
     * Test that generatePdf returns a download response when certificate exists.
     */
    public function test_downloads_pdf_when_certificate_exists()
    {
        // create instructor and category and course and user and certificate
        $instructor = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'instr_pdf_' . Str::random(6),
            'first_name' => 'Instr',
            'last_name' => 'Pdf',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'instructor',
        ]);

        $category = Category::create([
            'id' => Str::uuid()->toString(),
            'name' => 'PDF Category',
            'image' => null,
        ]);

        $course = Course::create([
            'id' => Str::uuid()->toString(),
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'PDF Course',
            'price' => 0,
            'is_discount_active' => false,
        ]);

        $student = User::create([
            'id' => Str::uuid()->toString(),
            'username' => 'stud_pdf_' . Str::random(6),
            'first_name' => 'Student',
            'last_name' => 'Pdf',
            'email' => Str::uuid()->toString() . '@example.test',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $certificateCode = 'TC-TESTPDF-' . Str::upper(Str::random(6));

        $certificate = Certificate::create([
            'id' => Str::uuid()->toString(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'certificate_code' => $certificateCode,
            'issued_at' => now(),
        ]);

        // Mock Pdf facade behavior to avoid actual PDF generation
        Pdf::shouldReceive('setOptions')->once();

        // Ensure the mock returned by loadView is an instance of Barryvdh\DomPDF\PDF
        $mockPdfObject = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $mockPdfObject->shouldReceive('setPaper')->withArgs(function ($paper, $orientation) {
            return $paper === 'a4' && $orientation === 'landscape';
        })->andReturnSelf();

        $fakePdfContent = 'FAKE_PDF_BYTES';
        $expectedFilenameFragment = 'certificate_' . Str::slug($student->full_name ?? ($student->first_name . ' ' . $student->last_name), '_');

        $mockPdfObject->shouldReceive('download')->andReturn(
            new Response($fakePdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename={$expectedFilenameFragment}.pdf",
            ])
        );

        // loadView can be called with any args; return our mock object (which matches expected return type)
        Pdf::shouldReceive('loadView')->andReturn($mockPdfObject);

        $controller = new CertificateController();
        $response = $controller->generatePdf($certificateCode);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($fakePdfContent, $response->getContent());

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment;', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);

        // cleanup Mockery expectations
        Mockery::close();
    }
}
