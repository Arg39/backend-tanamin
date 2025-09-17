<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\LessonCourse;
use App\Models\LessonProgress;
use App\Models\ModuleCourse;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;

class CertificateController extends Controller
{
    public function getInformationCertificate($courseId)
    {
        try {
            $user = JWTAuth::user();

            $course = Course::findOrFail($courseId);
            $modules = ModuleCourse::where('course_id', $course->id)->pluck('id');
            $lessons = LessonCourse::whereIn('module_id', $modules)->get();
            $lessonIds = $lessons->pluck('id');
            $progress = LessonProgress::where('user_id', $user->id)
                ->whereIn('lesson_id', $lessonIds)
                ->get();

            $allCompleted = $progress->count() === $lessons->count();

            if (!$allCompleted) {
                return new PostResource(false, "You haven't completed all lessons in this course.", null);
            } else {
                $certificate = Certificate::where('user_id', $user->id)
                    ->where('course_id', $course->id)
                    ->first();

                if ($certificate) {
                    $certificateData = $certificate->toArray();
                    $certificateData['user_name'] = "{$user->first_name} {$user->last_name}";
                    $certificateData['course_title'] = $course->title;
                    return new PostResource(true, "Certificate exists.", $certificateData);
                } else {
                    $certificateCode = 'TC-' . strtoupper(Str::random(3)) . '-' . strtoupper(Str::random(10));
                    $issuedAt = now();

                    $certificate = Certificate::create([
                        'user_id' => $user->id,
                        'course_id' => $course->id,
                        'certificate_code' => $certificateCode,
                        'issued_at' => $issuedAt,
                    ]);

                    $certificateData = $certificate->toArray();
                    $certificateData['user_fullname'] = "{$user->first_name} {$user->last_name}";
                    $certificateData['course_title'] = $course->title;
                    return new PostResource(true, "Certificate created.", $certificateData);
                }
            }
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response("Internal Server Error: " . $e->getMessage(), 500);
        }
    }

    public function generatePdf($courseId)
    {
        try {
            $user = JWTAuth::user();

            // 1. Validate course existence
            $course = Course::findOrFail($courseId);

            // 2. Check if certificate already exists
            $certificate = Certificate::where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->first();

            if (!$certificate) {
                // 3. Generate unique certificate code
                $certificateCode = 'TC-' . strtoupper(Str::random(3)) . '-' . strtoupper(Str::random(10));
                $issuedAt = now();

                // 4. Create certificate record
                $certificate = Certificate::create([
                    'user_id' => $user->id,
                    'course_id' => $courseId,
                    'certificate_code' => $certificateCode,
                    'issued_at' => $issuedAt,
                ]);
            } else {
                $certificateCode = $certificate->certificate_code;
                $issuedAt = $certificate->issued_at;
            }

            // 5. Generate QR code
            $qrCodeUrl = env('APP_URL') . "/api/certificates/{$certificateCode}/pdf";
            $logoPath = public_path('images/logo.png');
            if (!file_exists($logoPath)) {
                throw new \Exception("Logo file not found at: {$logoPath}");
            }
            $qrTempPath = storage_path('app/qr_temp.png');
            QrCode::format('png')
                ->size(400)
                ->margin(2)
                ->merge($logoPath, 0.2, true)
                ->generate($qrCodeUrl, $qrTempPath);
            $qrCodeBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($qrTempPath));

            // 6. Prepare PDF view data
            Pdf::setOptions([
                'font_dir' => public_path('fonts/'),
                'font_cache' => storage_path('fonts/'),
                'default_font' => 'Poppins',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

            $instructor = $course->instructor ? "{$course->instructor->first_name} {$course->instructor->last_name}" : '-';

            $viewData = [
                'user' => "{$user->first_name} {$user->last_name}",
                'course' => $course->title,
                'instructor' => $instructor,
                'issued_at' => $issuedAt ? $issuedAt->format('Y-m-d') : now()->format('Y-m-d'),
                'certificate_code' => $certificateCode,
                'qr_code' => $qrCodeBase64,
                'bg_image' => asset('images/certificate-bg.png'),
            ];

            file_put_contents(storage_path('app/pdf_debug.html'), view('certificate', $viewData)->render());

            $pdf = Pdf::loadView('certificate', $viewData)->setPaper('a4', 'landscape');
            $filename = 'certificate_' . Str::slug($viewData['user'], ' ') . '.pdf';

            // 7. Return PDF
            return $pdf->download($filename);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response("Internal Server Error: " . $e->getMessage(), 500);
        }
    }
}
