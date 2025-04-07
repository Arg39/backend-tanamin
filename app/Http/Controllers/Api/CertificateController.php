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
        $id_certificate = 'TESTING-ADS-1234567890';
        $qrCodeUrl = url("api/certificates/{$id_certificate}/pdf");

        // Path ke logo
        $logoPath = public_path('images/logo.png');

        // Pastikan file logo ada
        if (!file_exists($logoPath)) {
            throw new \Exception("Logo file not found at: {$logoPath}");
        }

        // Generate QR code dengan logo dan margin
        $qrCodeSvg = QrCode::format('svg')
            ->size(300)
            ->margin(2) // Tambahkan margin
            ->merge($logoPath, 0.2, true) // Tambahkan logo di tengah
            ->style('square') // Gaya kotak untuk QR code
            ->generate($qrCodeUrl);

        // Encode QR code ke base64
        $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrCodeSvg);

        // Generate PDF
        $pdf = Pdf::loadView('certificate', [
            'user' => 'test',
            'course' => 'test course',
            'issued_at' => '2023-10-01',
            'certificate_code' => 'ABC123',
            'qr_code' => $qrCodeBase64,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("certificate.pdf");
    }
}