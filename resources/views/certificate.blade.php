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
            font-family: 'Georgia', serif;
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
            padding: 100px;
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
    </style>
</head>
<body>
    <div class="container">
        <img src="{{ public_path('images/certificate-bg-2.png') }}" class="background">

        <div class="content">
            {{-- certificate for finish course in PT Tanamin --}}
            <div style="font-size: 20px; font-weight: bold; margin-bottom: 20px;">Certificate of Completion</div>
            p
            <div>This certifies that</div>
            <div class="name">{{ $user }}</div>
            has successfully completed the course
            <div class="course">“{{ $course }}”</div>

            <div class="footer">
                <div class="signature">
                    <img src="{{ public_path('images/signature-placeholder.png') }}"><br>
                    Authorized Signatory
                </div>
                <div class="qr-code">
                    <img src="{{ $qr_code }}" alt="QR Code">
                </div>
            </div>

            <div style="margin-top: 20px; font-size: 14px;">
                Issued on: {{ $issued_at }}
            </div>
        </div>
    </div>
</body>
</html>
