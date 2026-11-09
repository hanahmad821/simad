<?php

namespace App\Exports;

use App\Services\StudentImportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class StudentsTemplateExport implements \Maatwebsite\Excel\Concerns\Export, WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new class extends StringValueBinder implements FromArray, WithTitle, WithEvents, WithCustomValueBinder {
                public function title(): string { return 'Data Siswa'; }
                public function array(): array { return [array_values(StudentImportService::COLUMNS)]; }
                public function registerEvents(): array
                {
                    return [AfterSheet::class => function (AfterSheet $event) {
                        $sheet = $event->sheet->getDelegate();
                        $sheet->freezePane('E2');
                        $sheet->setShowGridlines(false);
                        $sheet->getStyle('A1:P1001')->getFont()->setName('Arial')->setSize(11);
                        $sheet->getStyle('A1:P1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                        $sheet->getStyle('A1:P1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF07583F');
                        $sheet->getStyle('A1:P1')->getAlignment()->setHorizontal('center')->setVertical('center');
                        $sheet->getRowDimension(1)->setRowHeight(28);
                        $sheet->getStyle('A2:P1001')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                        $sheet->getStyle('G2:G1001')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                        foreach (range('A', 'P') as $col) $sheet->getColumnDimension($col)->setWidth(23);
                        $sheet->getColumnDimension('D')->setWidth(32);
                        $sheet->getColumnDimension('L')->setWidth(45);
                        foreach (['E' => '"L,P"', 'M' => '"Ya,Tidak"', 'O' => '"Ya,Tidak"'] as $col => $values) {
                            $validation = new DataValidation();
                            $validation->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)
                                ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)
                                ->setErrorTitle('Pilihan tidak sesuai')->setError('Pilih salah satu nilai pada dropdown.')
                                ->setFormula1($values)->setSqref($col.'2:'.$col.'1001');
                            $sheet->setDataValidation($col.'2', $validation);
                        }
                    }];
                }
            },
            new class extends StringValueBinder implements FromArray, WithTitle, WithEvents, WithCustomValueBinder {
                public function title(): string { return 'Petunjuk'; }
                public function array(): array
                {
                    return [
                        ['PETUNJUK IMPOR SISWA'],
                        ['Isi sheet Data Siswa mulai baris 2. Jangan mengganti nama kolom pada baris 1.'],
                        ['Maksimal 1.000 siswa per file. Baris kosong dilewati. Nama Lengkap wajib diisi.'],
                        ['NIS, NISN, dan NIK harus unik dalam file dan belum terdaftar di aplikasi.'],
                        ['Nomor identitas dan telepon menggunakan format teks. Pertahankan nol di depan.'],
                        ['Jenis Kelamin: L atau P. Penerima PIP dan Pemilik KIP: Ya atau Tidak.'],
                        ['Tanggal Lahir: YYYY-MM-DD atau tanggal Excel, tanpa tanggal di masa depan.'],
                        ['Kolom opsional boleh kosong. Jangan menggunakan rumus pada sheet Data Siswa.'],
                        ['Contoh di bawah hanya petunjuk, bukan data siswa yang akan diimpor.'],
                        ['Setelah impor, tempatkan siswa melalui menu Kelas & Rombel.'],
                        [''],
                        ['Kolom', 'Contoh', 'Ketentuan'],
                        ['NIS', '000123', 'Opsional. Unik. Maksimal 30 karakter.'],
                        ['NISN', '0012345678', 'Opsional. Unik. Format teks.'],
                        ['NIK', '3302010101100001', 'Opsional. Angka dalam format teks. Unik.'],
                        ['Nama Lengkap', 'Siswa Contoh', 'Wajib. Maksimal 150 karakter.'],
                        ['Jenis Kelamin', 'L', 'Opsional. L = laki-laki, P = perempuan.'],
                        ['Tempat Lahir', 'Purwokerto', 'Opsional. Maksimal 100 karakter.'],
                        ['Tanggal Lahir', '2010-01-15', 'Opsional. YYYY-MM-DD atau tanggal Excel.'],
                        ['Nama Ayah', 'Ayah Contoh', 'Opsional. Maksimal 150 karakter.'],
                        ['Nama Ibu', 'Ibu Contoh', 'Opsional. Maksimal 150 karakter.'],
                        ['Nama Wali', 'Wali Contoh', 'Opsional. Maksimal 150 karakter.'],
                        ['Telepon Wali', '081234567890', 'Opsional. Format teks. Maksimal 30 karakter.'],
                        ['Alamat', 'Alamat siswa', 'Opsional. Maksimal 10.000 karakter.'],
                        ['Penerima PIP', 'Tidak', 'Kosong dianggap Tidak.'],
                        ['Nomor PIP', '000123456', 'Diisi jika Penerima PIP = Ya. Maksimal 50 karakter.'],
                        ['Pemilik KIP', 'Tidak', 'Kosong dianggap Tidak.'],
                        ['Nomor KIP', '000987654', 'Diisi jika Pemilik KIP = Ya. Maksimal 50 karakter.'],
                    ];
                }
                public function registerEvents(): array
                {
                    return [AfterSheet::class => function (AfterSheet $event) {
                        $sheet = $event->sheet->getDelegate();
                        $sheet->setShowGridlines(false);
                        $sheet->getStyle('A1:C28')->getFont()->setName('Arial')->setSize(11);
                        $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
                        $sheet->getColumnDimension('A')->setWidth(27);
                        $sheet->getColumnDimension('B')->setWidth(25);
                        $sheet->getColumnDimension('C')->setWidth(62);
                        foreach (range(2, 10) as $row) {
                            $sheet->mergeCells('A'.$row.':C'.$row);
                            $sheet->getRowDimension($row)->setRowHeight(25);
                        }
                        $sheet->getStyle('A12:C12')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                        $sheet->getStyle('A12:C12')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF07583F');
                        $sheet->getStyle('B13:B28')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                        $sheet->getStyle('A12:C28')->getAlignment()->setWrapText(true)->setVertical('center');
                        foreach (range(12, 28) as $row) $sheet->getRowDimension($row)->setRowHeight(32);
                    }];
                }
            },
        ];
    }
}
