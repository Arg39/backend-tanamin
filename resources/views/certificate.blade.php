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
            color: #deebdf;
        }
    </style>
</head>

<body>
    <div class="container">
        <img src="images/certificate-bg.png" class="background">

        <div class="content">
            <div
                style="width: 60%; height: 100%; position: absolute; right: 54px; top: 0; text-align: left; word-wrap: break-word; overflow-wrap: break-word; color: #006840; ">
                <div style="position: absolute; top: 0;">
                    <p style="font-size: 86px; font-weight: bold;">CERTIFICATE</p>
                    <p style="margin-top: -90px; font-size: 32px;">of completion</p>
                    <p style="margin-top: -6px; font-size: 50px; font-weight: bold;">
                        {{ $course }}</p>
                    <p style="font-size: 18px; margin-top: -32px;">instructor <span
                            style="font-weight: bold;">{{ $insructor }}</span></p>
                    <p style="line-height: 65%; font-size: 18px; margin-top: 20px;">awarded to :</p>
                    <p style="font-size: 40px; margin-top: -8px; font-weight: bold;">{{ $user }}</p>
                </div>
                <div style="position: absolute; bottom: 24px; display: flex; align-items: center;">
                    <img src="{{ $qr_code }}" alt="QR Code"
                        style="margin-right: 20px; width: 120px; height: 120px;">
                    <div style="margin-top: -10px; font-size: 16px;">
                        <p style="font-weight: regular; font-weight: regular; ">Verified by <span
                                style="font-weight: bold;">PT
                                Tanamin Bumi
                                Nusantara</span></p>
                        <p style="margin-top: -12px;">Course Code: {{ $certificate_code }}</p>
                    </div>
                </div>
            </div>
            @php
                $year = \Carbon\Carbon::parse($issued_at)->format('Y');
                $year_parts = str_split($year, 2);
            @endphp
            <div class="year"
                style="position: absolute; bottom: 0px; left: 50px; width:214px; height: 280px; color: #deebdf; font-weight: bold; text-align: center; letter-spacing: 16%; font-size: 100px; line-height: 20%; display: flex; justify-content: center; align-items: center; flex-direction: column;">
                <p>{{ $year_parts[0] }}</p>
                <p>{{ $year_parts[1] }}</p>
            </div>
        </div>
    </div>
</body>

</html>
