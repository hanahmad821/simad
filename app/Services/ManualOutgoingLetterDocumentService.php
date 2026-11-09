<?php

namespace App\Services;

use App\Models\OutgoingLetter;
use App\Models\SchoolSetting;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use PhpOffice\PhpWord\TemplateProcessor;

class ManualOutgoingLetterDocumentService
{
    public function generate(OutgoingLetter $letter): string
    {
        /*
        |--------------------------------------------------------------------------
        | Validasi
        |--------------------------------------------------------------------------
        */

        if ($letter->use_template) {
            throw new \RuntimeException(
                'Surat ini menggunakan template sistem.'
            );
        }

        if ($letter->status !== 'issued') {
            throw new \RuntimeException(
                'Surat belum diterbitkan.'
            );
        }

        if (!$letter->file) {
            throw new \RuntimeException(
                'Dokumen surat manual belum diupload.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | File sumber
        |--------------------------------------------------------------------------
        */

        $sourcePath = storage_path(
            'app/public/' . $letter->file
        );

        if (!file_exists($sourcePath)) {
            throw new \RuntimeException(
                'File dokumen surat tidak ditemukan.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Template Processor
        |--------------------------------------------------------------------------
        */

        $processor = new TemplateProcessor(
            $sourcePath
        );

        /*
        |--------------------------------------------------------------------------
        | Data Surat
        |--------------------------------------------------------------------------
        */

        $processor->setValue(
            'nomor_surat',
            $letter->nomor_surat ?? ''
        );

        $processor->setValue(
            'tanggal_surat',
            $letter->tanggal_surat?->translatedFormat('d F Y') ?? ''
        );

        $processor->setValue(
            'tujuan',
            $letter->tujuan ?? ''
        );

        $processor->setValue(
            'perihal',
            $letter->perihal ?? ''
        );

        $processor->setValue(
            'keterangan',
            $letter->keterangan ?? ''
        );

        /*
        |--------------------------------------------------------------------------
        | Data Madrasah
        |--------------------------------------------------------------------------
        */

        $this->fillSchoolData($processor);

        /*
        |--------------------------------------------------------------------------
        | QR Verification
        |--------------------------------------------------------------------------
        */

        $verificationUrl = route(
            'letters.verify',
            $letter->verification_token
        );

        $directory = storage_path(
            'app/generated/letters'
        );

        if (!is_dir($directory)) {
            mkdir(
                $directory,
                0755,
                true
            );
        }

        $qrPath = $directory .
            '/qr-manual-' .
            $letter->id .
            '.png';

        $qr = new Builder(
            writer: new PngWriter(),
            data: $verificationUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        $qrResult = $qr->build();

        $qrResult->saveToFile(
            $qrPath
        );

        /*
        |--------------------------------------------------------------------------
        | Masukkan QR ke DOCX
        |--------------------------------------------------------------------------
        |
        | Placeholder di Word:
        |
        | ${qr_code}
        |
        */

        $processor->setImageValue(
            'qr_code',
            [
                'path' => $qrPath,
                'width' => 90,
                'height' => 90,
                'ratio' => true,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Generate Final DOCX
        |--------------------------------------------------------------------------
        */

        $filename =
            'surat-' .
            $letter->id .
            '-final-' .
            now()->format('YmdHis') .
            '.docx';

        $outputPath =
            $directory .
            '/' .
            $filename;

        $processor->saveAs(
            $outputPath
        );
        /*
        |--------------------------------------------------------------------------
        | Simpan path final ke database
        |--------------------------------------------------------------------------
        */

        $relativePath =
            'generated/letters/' . $filename;

        $letter->update([
            'final_file' => $relativePath,
            'final_docx_file' => $relativePath,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Hapus QR temporary
        |--------------------------------------------------------------------------
        */

        if (file_exists($qrPath)) {
            unlink($qrPath);
        }

        /*
        |--------------------------------------------------------------------------
        | Hapus QR temporary
        |--------------------------------------------------------------------------
        */

        if (file_exists($qrPath)) {
            unlink($qrPath);
        }

        return $outputPath;
    }

    private function fillSchoolData(
        TemplateProcessor $processor
    ): void {
        $setting = SchoolSetting::query()->first();

        if (!$setting) {
            return;
        }

        $processor->setValue(
            'nama_madrasah',
            $setting->school_name ?? ''
        );

        /*
        |--------------------------------------------------------------------------
        | Field kepala madrasah
        |--------------------------------------------------------------------------
        |
        | Kalau kolom ini memang sudah ada di SchoolSetting.
        |
        */

        $processor->setValue(
            'kepala_nama',
            $setting->headmaster_name ?? ''
        );

        $processor->setValue(
            'kepala_nip',
            $setting->headmaster_nip ?? ''
        );

        $processor->setValue(
            'kepala_pangkat',
            $setting->headmaster_rank ?? ''
        );

        $processor->setValue(
            'kepala_jabatan',
            $setting->headmaster_position ?? ''
        );
    }
}
