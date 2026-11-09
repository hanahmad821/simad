<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class StudentImportService
{
    public const COLUMNS = [
        'nis' => 'NIS', 'nisn' => 'NISN', 'nik' => 'NIK',
        'full_name' => 'Nama Lengkap', 'gender' => 'Jenis Kelamin',
        'birth_place' => 'Tempat Lahir', 'birth_date' => 'Tanggal Lahir',
        'father_name' => 'Nama Ayah', 'mother_name' => 'Nama Ibu',
        'guardian_name' => 'Nama Wali', 'guardian_phone' => 'Telepon Wali',
        'address' => 'Alamat', 'pip_status' => 'Penerima PIP', 'pip_number' => 'Nomor PIP',
        'kip_status' => 'Pemilik KIP', 'kip_number' => 'Nomor KIP',
    ];

    public function read(string $path): array
    {
        try {
            $workbook = IOFactory::load($path);
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['file' => 'File Excel tidak dapat dibaca. Gunakan template dan simpan sebagai .xlsx atau .xls.']);
        }
        try {
            $sheet = $workbook->getSheetByName('Data Siswa') ?? $workbook->getSheet(0);
            $headers = [];
            $aliases = [];
            foreach (self::COLUMNS as $key => $label) {
                $aliases[$key] = $key;
                $aliases[Str::slug($label, '_')] = $key;
            }
            $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
            if ($lastColumn > 50 || $sheet->getHighestDataRow() > 10001) {
                throw ValidationException::withMessages(['file' => 'File terlalu besar. Gunakan maksimal 1.000 siswa dan kolom sesuai template.']);
            }
            for ($col = 1; $col <= $lastColumn; $col++) {
                $heading = Str::slug(trim((string) $sheet->getCell([$col, 1])->getValue()), '_');
                if (isset($aliases[$heading])) {
                    $key = $aliases[$heading];
                    if (in_array($key, $headers, true)) throw ValidationException::withMessages(['file' => 'Kolom '.$heading.' muncul lebih dari satu kali.']);
                    $headers[$col] = $key;
                }
            }
            if (! in_array('full_name', $headers, true)) {
                throw ValidationException::withMessages(['file' => 'Kolom Nama Lengkap tidak ditemukan pada baris pertama. Gunakan template yang disediakan.']);
            }
            $rows = [];
            for ($line = 2; $line <= $sheet->getHighestDataRow(); $line++) {
                $row = ['_row' => $line];
                foreach ($headers as $col => $key) {
                    $cell = $sheet->getCell([$col, $line]);
                    $value = $cell->getValue();
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        throw ValidationException::withMessages(['file' => 'Baris '.$line.', '.self::COLUMNS[$key].': gunakan nilai biasa, bukan rumus Excel.']);
                    }
                    if ($key === 'birth_date' && is_numeric($value) && Date::isDateTime($cell)) {
                        $value = Date::excelToDateTimeObject($value)->format('Y-m-d');
                    } elseif (in_array($key, ['nis', 'nisn', 'nik', 'guardian_phone', 'pip_number', 'kip_number'], true) && is_numeric($value) && $cell->getDataType() === DataType::TYPE_NUMERIC) {
                        if (strlen(sprintf('%.0f', $value)) > 15) throw ValidationException::withMessages(['file' => 'Baris '.$line.', '.self::COLUMNS[$key].': nomor panjang harus disimpan sebagai teks di Excel agar tidak terpotong.']);
                        $value = $cell->getFormattedValue();
                    }
                    $row[$key] = $value;
                }
                if (collect($row)->except('_row')->every(fn ($value) => $value === null || trim((string) $value) === '')) continue;
                $rows[] = $row;
                if (count($rows) > 1000) throw ValidationException::withMessages(['file' => 'Maksimal 1.000 siswa per impor. Pisahkan data menjadi beberapa file.']);
            }

            return $this->validateRows($rows);
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function validateRows(array $rows): array
    {
        if (empty($rows) || count($rows) > 1000) throw ValidationException::withMessages(['file' => 'Isi sedikitnya satu siswa dan maksimal 1.000 siswa per impor.']);
        $normalized = [];
        foreach ($rows as $index => $row) {
            $data = ['_row' => $row['_row'] ?? $index + 2];
            foreach (self::COLUMNS as $key => $label) {
                $value = isset($row[$key]) ? trim((string) $row[$key]) : '';
                $data[$key] = $value === '' ? null : $value;
            }
            $data['gender'] = match (mb_strtolower($data['gender'] ?? '')) {
                'l', 'male', 'laki-laki', 'laki laki' => 'male',
                'p', 'female', 'perempuan' => 'female',
                '' => null,
                default => $data['gender'],
            };
            foreach (['pip_status', 'kip_status'] as $key) {
                $data[$key] = match (mb_strtolower($data[$key] ?? '')) {
                    '1', 'ya', 'yes', 'true' => true,
                    '', '0', 'tidak', 'no', 'false' => false,
                    default => $data[$key],
                };
            }
            $normalized[] = $data;
        }
        $rules = [
            'full_name' => ['required', 'string', 'max:150'],
            'nis' => ['nullable', 'string', 'max:30', 'distinct', 'unique:students,nis'],
            'nisn' => ['nullable', 'string', 'max:30', 'distinct', 'unique:students,nisn'],
            'nik' => ['nullable', 'string', 'max:30', 'regex:/^[0-9]+$/', 'distinct', 'unique:students,nik'],
            'gender' => ['nullable', 'in:male,female'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'mother_name' => ['nullable', 'string', 'max:150'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:10000'],
            'pip_status' => ['boolean'], 'kip_status' => ['boolean'],
            'pip_number' => ['nullable', 'string', 'max:50'],
            'kip_number' => ['nullable', 'string', 'max:50'],
        ];
        $validator = Validator::make(['rows' => $normalized], collect($rules)->mapWithKeys(fn ($rule, $key) => ['rows.*.'.$key => $rule])->all(), [
            'required' => ':attribute wajib diisi.', 'max' => ':attribute melebihi panjang yang diizinkan.',
            'distinct' => ':attribute duplikat dalam file.', 'unique' => ':attribute sudah terdaftar di aplikasi.',
            'in' => ':attribute tidak sesuai pilihan. Jenis kelamin: L/P; PIP/KIP: Ya/Tidak.',
            'boolean' => ':attribute harus Ya atau Tidak.', 'date_format' => ':attribute harus berformat YYYY-MM-DD.',
            'before_or_equal' => ':attribute tidak boleh melewati hari ini.',
            'regex' => ':attribute hanya boleh berisi angka.',
        ], collect(self::COLUMNS)->mapWithKeys(fn ($label, $key) => ['rows.*.'.$key => $label])->all());
        if ($validator->fails()) {
            $messages = [];
            foreach ($validator->errors()->messages() as $key => $errors) {
                $index = (int) explode('.', $key)[1];
                foreach ($errors as $error) $messages[] = 'Baris '.$normalized[$index]['_row'].': '.$error;
            }
            throw ValidationException::withMessages(['file' => $messages]);
        }

        return $normalized;
    }

    public function store(array $rows): int
    {
        try {
            return DB::transaction(function () use ($rows) {
                $rows = $this->validateRows($rows);
                foreach ($rows as $row) {
                    unset($row['_row']);
                    foreach (['pip', 'kip'] as $program) if (! $row[$program.'_status']) $row[$program.'_number'] = null;
                    Student::create($row + ['student_code' => 'S-'.Str::ulid(), 'is_active' => true]);
                }

                return count($rows);
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages(['file' => 'Ada identitas siswa yang baru saja didaftarkan. Tinjau ulang file. Tidak ada data dari impor ini yang disimpan.']);
            }
            throw $exception;
        }
    }
}
