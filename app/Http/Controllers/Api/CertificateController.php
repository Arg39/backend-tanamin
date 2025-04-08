<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateController extends Controller
{
    public function generatePdf($id)
    {
        $id_certificate = 'TC-ADS-1234567890';
        $qrCodeUrl = env('APP_URL') . "/api/certificates/{$id_certificate}/pdf";

        // Path ke logo
        $logoPath = public_path('images/logo.png');

        // Pastikan file logo ada
        if (!file_exists($logoPath)) {
            throw new \Exception("Logo file not found at: {$logoPath}");
        }

        // Generate QR code dengan logo dan margin
        $qrCodeSvg = QrCode::format('svg')
            ->size(120)
            ->margin(2) // Tambahkan margin
            ->merge($logoPath, 0.2, true) // Tambahkan logo di tengah
            ->style('square') // Gaya kotak untuk QR code
            ->generate($qrCodeUrl);

        // Encode QR code ke base64
        $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrCodeSvg);

        Pdf::setOptions([
            'font_dir' => public_path('fonts/'), // Path ke folder font
            'font_cache' => storage_path('fonts/'), // Cache font
            'default_font' => 'Poppins', // Default font
        ]);

        // Generate PDF
        $pdf = Pdf::loadView('certificate', [
            'user' => 'Alfian Pabannu',
            'course' => 'Fullstack Mobile Developer',
            'insructor' => 'Bacimm darynth Ashornto',
            'issued_at' => '2025-10-01',
            'certificate_code' => $id_certificate,
            'qr_code' => $qrCodeBase64,
        ])->setPaper('a4', 'landscape');

        // see output without download
        return $pdf->stream("certificate.pdf");
        // return $pdf->download("certificate.pdf");
    }
}
