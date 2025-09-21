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
            font-family: Impact, Haettenschweiler, 'Arial Narrow Bold', sans-serif
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
            color: #006840;
        }
    </style>
</head>

<body>
    <div class="container">
        <img src="{{ public_path('images/certificate-bg.png') }}" class="background">

        <div class="content">
            <div
                style="width: 60%; height: 100%; position: absolute; right: 32px; top: 0; text-align: left; word-wrap: break-word; overflow-wrap: break-word;">
                <div style="position: absolute; top: 0;">
                    <p style="font-size: 86px; font-weight: bold;">CERTIFICATE</p>
                    <p style="margin-top: -90px; font-size: 24px;">of course completion</p>
                    <p style="margin-top: -12px; font-size: 36px; font-weight: bold;">{{ strtoupper($course) }}</p>
                    <p style="font-size: 18px; margin-top: -32px;">instructor <span
                            style="font-weight: bold;">{{ $instructor }}</span></p>
                    <p style="line-height: 65%; font-size: 18px; margin-top: 20px;">awarded to :</p>
                    <p style="font-size: 36px; margin-top: -8px; font-weight: bold;">{{ $user }}</p>
                </div>

                <div style="position: absolute; bottom: 24px; display: flex; align-items: center;">
                    <img src="{{ $qr_code_base64 }}" alt="QR Code" width="120">
                    <div style="font-size: 16px;">
                        <p>Verified by <strong>PT Tanamin Bumi Nusantara</strong></p>
                        <p style="margin-top: -12px;">Course Code: {{ $certificate_code }}</p>
                    </div>
                </div>
            </div>

            @php
                $year = \Carbon\Carbon::parse($issued_at)->format('Y');
                $year_parts = str_split($year, 2);
            @endphp
            <div class="year"
                style="position: absolute; bottom: 0px; left: 54px; width:214px; height: 280px; color: #deebdf; font-weight: bold; text-align: center; letter-spacing: 16%; font-size: 100px; line-height: 20%; display: flex; justify-content: center; align-items: center; flex-direction: column;">
                <p>{{ $year_parts[0] }}</p>
                <p>{{ $year_parts[1] }}</p>
            </div>
        </div>
    </div>
</body>

</html>
