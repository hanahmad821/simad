<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <style>
        @page {
            size: A4;
            /* margin: 2cm 2.5cm 2cm 2.5cm; */
            margin: 1cm;
            margin-top: 0.2cm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
        }

        /* =========================
           KOP SURAT
           ========================= */

        .kop {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .kop td {
            vertical-align: middle;
            padding: 0;
        }

        .kop-logo {
            width: 28%;
            text-align: center;
            vertical-align: middle;
        }

        .kop-logo img {
            width: 130px;
            height: auto;
        }

        .kop-info {
            width: 72%;
            text-align: center;
            color: #087443;
        }

        .kop-foundation {
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .kop-title {
            margin: 0;
            font-size: 14pt;
            font-weight: bold;
            line-height: 1.05;
        }

        .kop-accreditation {
            margin: 1px 0 0;
            font-size: 11pt;
            font-weight: bold;
            line-height: 1;
        }

        .kop-address {
            font-size: 9pt;
            margin-top: 6px;
            line-height: 1.3;
        }

        .kop-line {
            border-top: 4px solid #087443;
            margin-top: 8px;
            margin-bottom: 20px;
        }

        .content {
            width: 100%;
        }

        .meta {
            margin-bottom: 20px;
        }

        .meta td {
            vertical-align: top;
        }

        .meta-label {
            width: 90px;
        }

        .letter-body p { margin: 0 0 10pt; }
        .letter-body ol, .letter-body ul { margin: 6pt 0; padding-left: 22pt; }
        .letter-body ol { list-style-type: decimal; }
        .letter-body ol ol { list-style-type: lower-alpha; }
        .letter-body ol ol ol { list-style-type: lower-roman; }
        .letter-body ul { list-style-type: disc; }
        .letter-body li { margin-bottom: 4pt; }
        .letter-body li > p { margin: 0 0 3pt; }
        .signature {
            margin-top: 40px;
            margin-left: 60%;
            width: 40%;
            text-align: center;
        }
    </style>
</head>

<body>

    @include('pdf.partials.madrasah-letterhead')

    @yield('content')

</body>

</html>
