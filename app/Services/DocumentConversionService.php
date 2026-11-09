<?php

namespace App\Services;

use App\Models\OutgoingLetter;
use Symfony\Component\Process\Process;

class DocumentConversionService
{
    public function convertToPdf(
        string $docxPath,
        OutgoingLetter $letter
    ): string {
        if (! file_exists($docxPath)) {
            throw new \RuntimeException(
                'File DOCX untuk dikonversi tidak ditemukan.'
            );
        }

        $directory = storage_path(
            'app/generated/letters'
        );

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        /*
        |--------------------------------------------------------------------------
        | LibreOffice
        |--------------------------------------------------------------------------
        */

        $process = new Process([
            'soffice',
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $directory,
            $docxPath,
        ]);

        $process->setTimeout(120);

        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(
                'Gagal mengonversi DOCX ke PDF: ' .
                trim($process->getErrorOutput())
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Nama PDF hasil LibreOffice
        |--------------------------------------------------------------------------
        |
        | LibreOffice menggunakan nama file DOCX sebagai nama PDF.
        |
        */

        $sourceFilename = pathinfo(
            $docxPath,
            PATHINFO_FILENAME
        );

        $generatedPdfPath =
            $directory .
            '/' .
            $sourceFilename .
            '.pdf';

        if (! file_exists($generatedPdfPath)) {
            throw new \RuntimeException(
                'File PDF hasil konversi tidak ditemukan.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Nama final PDF
        |--------------------------------------------------------------------------
        */

        $filename =
            'surat-' .
            $letter->id .
            '-final-' .
            now()->format('YmdHis') .
            '.pdf';

        $finalPath =
            $directory .
            '/' .
            $filename;

        /*
        |--------------------------------------------------------------------------
        | Rename hasil konversi
        |--------------------------------------------------------------------------
        */

        if (! rename(
            $generatedPdfPath,
            $finalPath
        )) {
            throw new \RuntimeException(
                'Gagal menyimpan file PDF final.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Simpan path ke database
        |--------------------------------------------------------------------------
        */

        $letter->update([
            'final_pdf_file' =>
                'generated/letters/' . $filename,
        ]);

        return $finalPath;
    }
}
