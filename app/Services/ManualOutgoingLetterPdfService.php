<?php

namespace App\Services;

use App\Models\OutgoingLetter;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;

class ManualOutgoingLetterPdfService
{
    public function generate(OutgoingLetter $letter): string
    {
        if ($letter->getAttribute('use_template')) {
            throw new \RuntimeException('Surat ini menggunakan template, bukan surat manual.');
        }
        if (! $letter->file) {
            throw new \RuntimeException('File PDF surat manual belum tersedia.');
        }
        if ($letter->usesWetSignature()) {
            $source = Storage::disk('public')->path($letter->file);
            if (! is_file($source)) {
                throw new \RuntimeException('File PDF surat manual tidak ditemukan.');
            }
            $relative = 'generated/letters/surat-'.$letter->id.'-print-'.Str::uuid().'.pdf';
            $output = storage_path('app/'.$relative);
            if (! is_dir(dirname($output))) {
                mkdir(dirname($output), 0755, true);
            }
            if (! copy($source, $output)) {
                throw new \RuntimeException('Dokumen cetak gagal disimpan.');
            }
            $letter->update(['final_pdf_file' => $relative]);
            return $output;
        }
        if (! $letter->verification_token) {
            throw new \RuntimeException('Verification token belum tersedia.');
        }

        $sourcePath = Storage::disk('public')->path($letter->file);
        $anchor = app(PdfQrAnchorService::class)->locate($sourcePath);
        $directory = storage_path('app/generated/letters');
        $qrDirectory = storage_path('app/generated/qr');
        foreach ([$directory, $qrDirectory] as $path) {
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        $suffix = Str::uuid()->toString();
        $qrPath = $qrDirectory.'/qr-'.$letter->id.'-'.$suffix.'.png';
        $filename = 'surat-'.$letter->id.'-final-'.$suffix.'.pdf';
        $outputPath = $directory.'/'.$filename;

        $pdf = new Fpdi;
        try {
            $qr = new Builder(
                writer: new PngWriter,
                data: route('letters.verify', $letter->verification_token),
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 600,
                margin: 24,
            );
            $qr->build()->saveToFile($qrPath);

            $pdf->SetAutoPageBreak(false);
            $pageCount = $pdf->setSourceFile($sourcePath);
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $size = $pdf->getTemplateSize($templateId);
                if (! is_array($size)) {
                    throw new \RuntimeException('Ukuran halaman PDF tidak dapat dibaca.');
                }
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                if ($pageNumber === $anchor['page']) {
                    // Cover the visible marker, then place the QR including its white quiet zone.
                    $pdf->SetFillColor(255, 255, 255);
                    $pdf->Rect($anchor['x'], $anchor['y'], $anchor['size'], $anchor['size'], 'F');
                    $pdf->Image($qrPath, $anchor['x'], $anchor['y'], $anchor['size'], $anchor['size'], 'PNG');
                }
            }

            $pdf->Output('F', $outputPath);
            $letter->update(['final_pdf_file' => 'generated/letters/'.$filename]);

            return $outputPath;
        } catch (\Throwable $e) {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }
            throw $e;
        } finally {
            $pdf->cleanUp();
            if (is_file($qrPath)) {
                unlink($qrPath);
            }
        }
    }
}
