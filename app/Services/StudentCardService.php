<?php

namespace App\Services;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class StudentCardService
{
    public function qrCodeDataUri(?string $code): ?string
    {
        if (! $code || ! preg_match('/^[\x20-\x7E]{1,30}$/D', $code)) {
            return null;
        }

        $qrCode = new QrCode(
            data: $code,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 240,
            margin: 48,
        );

        return (new SvgWriter)->write($qrCode)->getDataUri();
    }

    public function madikaLogoDataUri(): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(public_path('brand/madika/logos/madika-compact.svg')));
    }
}
