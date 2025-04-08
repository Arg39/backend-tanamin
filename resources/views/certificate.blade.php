<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Certificate</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
        }

        .container {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .content {
            position: relative;
            z-index: 1;
            text-align: center;
            /* padding: 100px; */
            color: #deebdf;
        }

        .name {
            font-size: 36px;
            font-weight: bold;
            margin: 20px 0;
        }

        .course {
            font-size: 24px;
            font-style: italic;
            margin: 20px 0;
        }

        .footer {
            margin-top: 80px;
            display: flex;
            justify-content: space-between;
        }

        .signature {
            text-align: center;
            font-size: 14px;
        }

        .signature img {
            width: 120px;
        }

        .qr-code img {
            width: 100px;
        }

        .year {
            position: absolute;
            bottom: 0px;
            left: 95px;
            font-weight: 800;
            font-size: 95px;
            letter-spacing: 16px;
            color: #F6FCDF;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            gap: 0;
            /* Tambahkan ini untuk mengatur jarak antar elemen */
        }

        .year div:first-child {
            margin-bottom: -80px;
        }
    </style>
</head>

<body>
    <div class="container">
        {{-- <img src="{{ public_path('images/certificate-bg.png') }}" class="background"> --}}
        <img src="images/certificate-bg.png" class="background">

        <div class="content">
            <div
                style="width: 60%; height: 100%; position: absolute; right: 54px; top: 0; text-align: left; word-wrap: break-word; overflow-wrap: break-word; color: #006840; ">
                <div style="position: absolute; top: 0;">
                    <p style="font-size: 80px; font-weight: bold; ">CERTIFICATE</p>
                    <p style="margin-top: -100px; font-weight: regularmont; font-size: 30px;">of completion</p>
                    <p style="margin-top: -16px; font-weight: regularmont; font-size: 50px; line-height: 65%">
                        {{ $course }}</p>
                    <p style="line-height: 65%; font-size: 18px; margin-top: -32px;">instructor <span
                            style="font-weight: bold;">{{ $insructor }}</span></p>
                    <p style="line-height: 65%; font-size: 18px; margin-top: 24px;">awarded to</p>
                    <p style="font-size: 40px; margin-top: -16px; font-weight: bold;">{{ $user }}</p>
                </div>
                <div style="position: absolute; bottom: 40px; display: flex; align-items: center;">
                    <img src="{{ $qr_code }}" alt="QR Code"
                        style="margin-right: 20px; width: 100px; height: 100px;">
                    <div>
                        <p style="margin: 0;">Verified by <span style="font-weight: bold;">PT Tanamin Bumi
                                Nusantara</span></p>
                        <p style="margin: 0; font-size: 14px;">Course Code: {{ $certificate_code }}</p>
                    </div>
                </div>
            </div>
        </div>

        @php
            $year = \Carbon\Carbon::parse($issued_at)->format('Y');
        @endphp
        <div class="year">
            <div>{{ substr($year, 0, 2) }}</div>
            <div>{{ substr($year, 2, 2) }}</div>
        </div>
    </div>
</body>

</html>
