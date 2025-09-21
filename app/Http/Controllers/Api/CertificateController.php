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
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

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

    public function generatePdf($certificateCode)
    {
        try {
            $certificate = Certificate::where('certificate_code', $certificateCode)->first();
            if (!$certificate) {
                return response("Certificate not found.", 404);
            }

            $user = $certificate->user;
            $course = $certificate->course;

            $qrCodeUrl = env('APP_FE_URL') . "/certificate/verify/{$certificateCode}";

            $qr = QrCode::create($qrCodeUrl)
                ->setSize(160)
                ->setMargin(4);
            $writer = new PngWriter();
            $qrResult = $writer->write($qr);
            $qrCodeBase64 = "data:image/png;base64," . base64_encode($qrResult->getString());

            Pdf::setOptions([
                'font_dir' => public_path('fonts/'),
                'font_cache' => storage_path('fonts/'),
                'default_font' => 'Poppins',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

            $instructor = $course->instructor ? $course->instructor->full_name : '-';

            $issuedAt = $certificate->issued_at
                ? (is_a($certificate->issued_at, Carbon::class)
                    ? $certificate->issued_at->format('Y-m-d')
                    : Carbon::parse($certificate->issued_at)->format('Y-m-d'))
                : now()->format('Y-m-d');

            $viewData = [
                'user' => $user->full_name,
                'course' => $course->title,
                'instructor' => $instructor,
                'issued_at' => $issuedAt,
                'certificate_code' => $certificateCode,
                'qr_code_base64' => $qrCodeBase64,
            ];

            $pdf = Pdf::loadView('certificate', $viewData)->setPaper('a4', 'landscape');
            $filename = 'certificate_' . Str::slug($viewData['user'], '_') . '.pdf';

            return $pdf->download($filename);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response("Internal Server Error: " . $e->getMessage(), 500);
        }
    }
}
