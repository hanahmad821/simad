<?php

namespace App\Services;

use App\Models\OutgoingLetter;
use App\Models\SchoolSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Str;

class OutgoingLetterPdfService
{
    public function generate(
        OutgoingLetter $letter
    ): string {
        $letter->loadMissing('letterType');

        $definition = app(LetterTemplateService::class)->definition($letter);
        $view = ($definition['engine'] ?? null) === 'editor' ? 'pdf.outgoing-editor' : ($definition['template_view'] ?? null);

        if (! $view) {
            throw new \RuntimeException(
                'Template PDF untuk jenis surat ini belum tersedia.'
            );
        }

        $school = SchoolSetting::query()->first();

        if (! $school) {
            throw new \RuntimeException(
                'Data School Setting belum tersedia.'
            );
        }

        if (! $letter->usesWetSignature() && ! $letter->verification_token) {
            throw new \RuntimeException(
                'Verification token belum tersedia.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | QR Code
        |--------------------------------------------------------------------------
        */

        $qrPath = null;
        if (! $letter->usesWetSignature()) {
            $verificationUrl = route(
                'letters.verify',
                $letter->verification_token
            );

            $qrDirectory = storage_path(
                'app/generated/qr'
            );

            if (! is_dir($qrDirectory)) {
                mkdir($qrDirectory, 0755, true);
            }

            $qrFilename =
                'qr-'.
                $letter->id.
                '-'.
                now()->format('YmdHis').
                '.png';

            $qrPath = $qrDirectory.'/'.$qrFilename;

            $qr = new Builder(
                writer: new PngWriter,
                data: $verificationUrl,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 300,
                margin: 10,
            );

            $qrResult = $qr->build();

            $qrResult->saveToFile($qrPath);
        }

        /*
        |--------------------------------------------------------------------------
        | Render PDF
        |--------------------------------------------------------------------------
        */

        $pdf = Pdf::loadView($view, [
            'letter' => $letter,
            'school' => $school,
            'data' => $letter->data ?? [],
            'preview' => false,
            'qrPath' => $qrPath,
            'template' => $definition,
            'body' => ($definition['engine'] ?? null) === 'editor'
                ? app(LetterTemplateContentService::class)->render($definition['body_html'], $definition['values'] ?? app(LetterTemplateContentService::class)->values($letter, $definition['fields'])) : '',
        ]);

        $pdf->setPaper('a4', 'portrait');

        /*
        |--------------------------------------------------------------------------
        | Output
        |--------------------------------------------------------------------------
        */

        $directory = storage_path(
            'app/generated/letters'
        );

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename =
            'surat-'.
            $letter->id.
            '-final-'.
            now()->format('YmdHis').
            '.pdf';

        $path = $directory.'/'.$filename;

        $pdf->save($path);

        /*
        |--------------------------------------------------------------------------
        | Simpan path final
        |--------------------------------------------------------------------------
        */

        $letter->update([
            'final_pdf_file' => 'generated/letters/'.$filename,
        ]);

        /*
        |--------------------------------------------------------------------------
        | QR hanya file sementara
        |--------------------------------------------------------------------------
        */

        if ($qrPath && file_exists($qrPath)) {
            unlink($qrPath);
        }

        return $path;
    }

    public function preview(OutgoingLetter $letter): string
    {
        $letter->loadMissing('letterType');

        $definition = app(LetterTemplateService::class)->definition($letter);
        $view = ($definition['engine'] ?? null) === 'editor' ? 'pdf.outgoing-editor' : ($definition['template_view'] ?? null);

        if (! $view) {
            throw new \RuntimeException(
                'Template PDF untuk jenis surat ini belum tersedia.'
            );
        }

        $school = SchoolSetting::query()->first();

        if (! $school) {
            throw new \RuntimeException(
                'Data School Setting belum tersedia.'
            );
        }

        $pdf = Pdf::loadView($view, [
            'letter' => $letter,
            'school' => $school,
            'data' => $letter->data ?? [],
            'preview' => true,
            'qrPath' => null,
            'template' => $definition,
            'body' => ($definition['engine'] ?? null) === 'editor'
                ? app(LetterTemplateContentService::class)->render($definition['body_html'], $definition['values'] ?? app(LetterTemplateContentService::class)->values($letter, $definition['fields'])) : '',
        ]);

        $pdf->setPaper('a4', 'portrait');

        $directory = storage_path(
            'app/generated/previews'
        );

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename =
            'preview-surat-'.
            $letter->id.
            '-'.
            Str::uuid().
            '.pdf';

        $path = $directory.'/'.$filename;

        $pdf->save($path);

        return $path;
    }
}
