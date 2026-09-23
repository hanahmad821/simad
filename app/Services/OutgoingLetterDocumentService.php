<?php

namespace App\Services;

use App\Models\OutgoingLetter;
use App\Models\Student;
use App\Models\SchoolSetting;
use PhpOffice\PhpWord\TemplateProcessor;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
class OutgoingLetterDocumentService
{
    public function generate(OutgoingLetter $letter): string
    {
        $letter->loadMissing('letterType');

        $template = $letter->letterType?->template_file;

        if (!$template) {
            throw new \RuntimeException(
                'Template Word untuk jenis surat ini belum tersedia.'
            );
        }

        $templatePath = storage_path('app/templates/' . $template);

        if (!file_exists($templatePath)) {
            throw new \RuntimeException(
                'File template tidak ditemukan.'
            );
        }

        $processor = new TemplateProcessor($templatePath);

        /*
         * Data surat
         */
        $processor->setValue(
            'nomor_surat',
            $letter->nomor_surat
        );

        $processor->setValue(
            'tanggal_surat',
            $letter->tanggal_surat?->translatedFormat('d F Y') ?? ''
        );

        $processor->setValue(
            'tujuan',
            $letter->tujuan
        );

        $processor->setValue(
            'perihal',
            $letter->perihal
        );

        $processor->setValue(
            'isi',
            $letter->isi ?? ''
        );

        $processor->setValue(
            'keterangan',
            $letter->keterangan ?? ''
        );

        /*
         * Data dinamis
         */
        foreach ($letter->letterType?->fields ?? [] as $field) {
            $name = $field['name'] ?? '';
            $type = $field['type'] ?? 'text';

            if (!$name) {
                continue;
            }

            $value = data_get($letter->data ?? [], $name);

            if ($type === 'student' && $value) {
                $this->fillStudentData(
                    $processor,
                    (int) $value
                );

                continue;
            }

            if ($type === 'repeater') {
                $value = is_array($value)
                    ? implode("\n", $value)
                    : '';
            }

            $processor->setValue(
                $name,
                $value ?? ''
            );
        }

        /*
         * Data madrasah / kepala madrasah
         */
        $this->fillSchoolData($processor);

        /*
         * Output
         */
        $directory = storage_path('app/generated/letters');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if ($letter->status !== 'issued') {
            abort(403, 'Surat belum diterbitkan.');
        }

        $verificationUrl = route(
            'letters.verify',
            $letter->verification_token
        );

        $qrPath = storage_path(
            'app/generated/letters/qr-' . $letter->id . '.png'
        );

        $qrDirectory = dirname($qrPath);

        if (!is_dir($qrDirectory)) {
            mkdir($qrDirectory, 0755, true);
        }

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

        $qrResult->saveToFile($qrPath);

        $processor->setImageValue('qr_code', [
            'path' => $qrPath,
            'width' => 90,
            'height' => 90,
            'ratio' => true,
        ]);

        $filename = 'surat-' . $letter->id . '-' . now()->format('YmdHis') . '.docx';

        $outputPath = $directory . '/' . $filename;

        $processor->saveAs($outputPath);

        if (file_exists($qrPath)) {
            unlink($qrPath);
        }
        return $outputPath;
    }

    private function fillStudentData(
        TemplateProcessor $processor,
        int $studentId
    ): void {
        $student = Student::query()
            ->with('classHistories.schoolClass')
            ->find($studentId);

        if (!$student) {
            return;
        }

        $processor->setValue(
            'student_nama',
            $student->full_name
        );

        $processor->setValue(
            'student_nisn',
            $student->nisn ?? ''
        );

        $ttl = $student->birth_place;

        if ($student->birth_date) {
            $ttl .= ', ' . $student->birth_date->translatedFormat('d F Y');
        }

        $processor->setValue(
            'student_ttl',
            $ttl
        );

        $schoolClass = $student
            ->classHistories
            ->first()
                ?->schoolClass;

        $processor->setValue(
            'student_kelas',
            $schoolClass?->name ?? ''
        );
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
