<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateController extends Controller
{
    public function generatePdf($id)
    {
        try {
            Log::info("generatePdf called with ID: {$id}");

            $id_certificate = 'TC-ADS-1234567890';
            $qrCodeUrl = env('APP_URL') . "/api/certificates/{$id_certificate}/pdf";

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

            Pdf::setOptions([
                'font_dir' => public_path('fonts/'),
                'font_cache' => storage_path('fonts/'),
                'default_font' => 'Poppins',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

            $viewData = [
                'user' => 'George Smith dawning',
                'course' => 'Machine Learning for Beginners 2024',
                'insructor' => 'Bacimm darynth Ashornto',
                'issued_at' => '2024-10-01',
                'certificate_code' => $id_certificate,
                'qr_code' => $qrCodeBase64,
                'bg_image' => asset('images/certificate-bg.png'),
            ];

            file_put_contents(storage_path('app/pdf_debug.html'), view('certificate', $viewData)->render());

            $pdf = Pdf::loadView('certificate', $viewData)->setPaper('a4', 'landscape');

            $filename = 'certificate_' . Str::slug($viewData['user'], ' ') . '.pdf'; // Nama file diubah menjadi "certificate_(nama)"
            $pdf->save(storage_path("app/{$filename}"));

            Log::info("PDF saved to: storage/app/{$filename}");

            return $pdf->download($filename);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response("Internal Server Error: " . $e->getMessage(), 500);
        }
    }
}
