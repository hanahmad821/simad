<?php

use App\Exports\StudentsTemplateExport;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\User;
use App\Services\StudentImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function studentExcelFile(array $rows, ?array $headings = null): UploadedFile
{
    $book = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    $sheet->setTitle('Data Siswa');
    $sheet->fromArray($headings ?? array_values(StudentImportService::COLUMNS));
    foreach ($rows as $index => $row) {
        foreach (array_keys(StudentImportService::COLUMNS) as $col => $key) {
            if (isset($row[$key])) $sheet->getCell([$col + 1, $index + 2])->setValueExplicit((string) $row[$key], DataType::TYPE_STRING);
        }
    }
    $path = tempnam(sys_get_temp_dir(), 'students-test-');
    (new Xlsx($book))->save($path);
    $content = file_get_contents($path);
    unlink($path);
    $book->disconnectWorksheets();

    return UploadedFile::fake()->createWithContent('siswa.xlsx', $content);
}

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->service = app(StudentImportService::class);
});

it('offers import from the student list and downloads a blank template with instructions', function () {
    Livewire::test('admin.students.index')->assertSee('Impor Excel');
    Livewire::test('admin.students.import')->call('downloadTemplate')->assertFileDownloaded('template-data-siswa.xlsx');
    $path = tempnam(sys_get_temp_dir(), 'template-test-');
    file_put_contents($path, Excel::raw(new StudentsTemplateExport(), \Maatwebsite\Excel\Excel::XLSX));
    $book = IOFactory::load($path);
    expect($book->getSheetNames())->toBe(['Data Siswa', 'Petunjuk'])
        ->and($book->getSheet(0)->getCell('D1')->getValue())->toBe('Nama Lengkap')
        ->and($book->getSheet(0)->getCell('D2')->getValue())->toBeIn([null, ''])
        ->and($book->getSheet(0)->getStyle('A2')->getNumberFormat()->getFormatCode())->toBe('@')
        ->and($book->getSheet(0)->getCell('E2')->getDataValidation()->getFormula1())->toBe('"L,P"')
        ->and($book->getSheet(1)->getCell('B15')->getValue())->toBe('3302010101100001');
    $book->disconnectWorksheets();
    unlink($path);
});

it('previews before storing and preserves text identifiers without placing students into classes', function () {
    $file = studentExcelFile([
        ['full_name' => 'Siswa Excel', 'nis' => '000123', 'nisn' => '0012345678', 'nik' => '3302010101100001',
            'guardian_phone' => '081234567890', 'gender' => 'L', 'birth_date' => '2010-01-15', 'pip_status' => 'Ya', 'pip_number' => '000999'],
        ['full_name' => 'Siswa Kedua', 'gender' => 'P', 'kip_status' => 'Tidak'],
    ]);
    $component = Livewire::test('admin.students.import')->set('file', $file)->call('previewData')->assertHasNoErrors()
        ->assertSee('Siswa Excel')->assertSee('000123');
    expect(Student::count())->toBe(0);
    $component->call('import')->assertHasNoErrors()->assertSet('preview', []);
    $student = Student::where('full_name', 'Siswa Excel')->firstOrFail();
    expect(Student::count())->toBe(2)->and($student->nis)->toBe('000123')->and($student->nisn)->toBe('0012345678')
        ->and($student->nik)->toBe('3302010101100001')->and($student->guardian_phone)->toBe('081234567890')
        ->and($student->gender)->toBe('male')->and($student->pip_status)->toBeTrue()->and($student->pip_number)->toBe('000999')
        ->and(StudentClassHistory::count())->toBe(0)->and(strlen($student->student_code))->toBeLessThanOrEqual(30);
    $component->call('import')->assertHasErrors('file');
    expect(Student::count())->toBe(2);
});

it('skips blank rows and accepts legacy English column headings', function () {
    $file = studentExcelFile([[], ['full_name' => 'Siswa Baris Tiga'], []], array_keys(StudentImportService::COLUMNS));
    $rows = $this->service->read($file->getRealPath());
    expect($rows)->toHaveCount(1)->and($rows[0]['_row'])->toBe(3);
    expect($this->service->store($rows))->toBe(1);
});

it('reports invalid rows without saving any student', function (array $invalid) {
    $file = studentExcelFile([['full_name' => 'Siswa Valid'], $invalid]);
    Livewire::test('admin.students.import')->set('file', $file)->call('previewData')->assertHasErrors('file')->assertSee('Baris 3');
    expect(Student::count())->toBe(0);
})->with([
    'missing name' => [['nis' => 'BAD']],
    'bad gender' => [['full_name' => 'Siswa Salah', 'gender' => 'X']],
    'bad date' => [['full_name' => 'Siswa Salah', 'birth_date' => '2026-02-30']],
    'bad flag' => [['full_name' => 'Siswa Salah', 'pip_status' => 'Mungkin']],
    'bad nik' => [['full_name' => 'Siswa Salah', 'nik' => '3302xxxx']],
]);

it('rejects duplicate identities within the file and existing identities including alumni', function () {
    $file = studentExcelFile([['full_name' => 'A', 'nis' => '001'], ['full_name' => 'B', 'nis' => '001']]);
    Livewire::test('admin.students.import')->set('file', $file)->call('previewData')->assertHasErrors('file')->assertSee('duplikat');
    Student::create(['student_code' => 'ARCHIVED', 'full_name' => 'Alumni', 'nis' => '001', 'is_active' => false, 'exit_status' => 'graduated']);
    Livewire::test('admin.students.import')->set('file', studentExcelFile([['full_name' => 'Baru', 'nis' => '001']]))
        ->call('previewData')->assertHasErrors('file')->assertSee('sudah terdaftar');
    expect(Student::count())->toBe(1);
});

it('revalidates database uniqueness when saving a previously valid preview', function () {
    $component = Livewire::test('admin.students.import')
        ->set('file', studentExcelFile([['full_name' => 'Pertama', 'nis' => '1'], ['full_name' => 'Kedua', 'nis' => '2']]))
        ->call('previewData')->assertHasNoErrors();
    Student::create(['student_code' => 'CONCURRENT', 'full_name' => 'Sudah diinput operator lain', 'nis' => '2']);
    $component->call('import')->assertHasErrors('file');
    expect(Student::count())->toBe(1)->and(Student::where('nis', '1')->exists())->toBeFalse();
});

it('resets preview when the uploaded file changes', function () {
    $component = Livewire::test('admin.students.import')->set('file', studentExcelFile([['full_name' => 'Pertama']]))
        ->call('previewData')->assertHasNoErrors();
    $component->set('file', studentExcelFile([['full_name' => 'Kedua']]))->assertSet('preview', [])
        ->call('import')->assertHasErrors('file');
    expect(Student::count())->toBe(0);
});

it('rejects an empty template or missing required header', function () {
    Livewire::test('admin.students.import')->set('file', studentExcelFile([]))->call('previewData')->assertHasErrors('file');
    $file = studentExcelFile([['full_name' => 'Siswa']], ['Kolom tidak dikenal']);
    expect(fn () => $this->service->read($file->getRealPath()))->toThrow(ValidationException::class);
});

it('blocks non-admin access', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.students.import'))->assertForbidden();
})->with(['staff', 'teacher', 'headmaster']);

it('accepts real Excel dates and rejects formulas or rounded long identifiers', function () {
    $file = studentExcelFile([['full_name' => 'Tanggal Excel']]);
    $book = IOFactory::load($file->getRealPath());
    $sheet = $book->getSheet(0);
    $sheet->setCellValueExplicit('G2', \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new DateTime('2010-01-15')), DataType::TYPE_NUMERIC);
    $sheet->getStyle('G2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
    (new Xlsx($book))->save($file->getRealPath());
    expect($this->service->read($file->getRealPath())[0]['birth_date'])->toBe('2010-01-15');
    $sheet->setCellValueExplicit('C2', 3302010101100001, DataType::TYPE_NUMERIC);
    (new Xlsx($book))->save($file->getRealPath());
    expect(fn () => $this->service->read($file->getRealPath()))->toThrow(ValidationException::class, 'nomor panjang');
    $sheet->setCellValueExplicit('C2', null, DataType::TYPE_NULL);
    $sheet->setCellValueExplicit('D2', '="Nama dari rumus"', DataType::TYPE_FORMULA);
    (new Xlsx($book))->save($file->getRealPath());
    expect(fn () => $this->service->read($file->getRealPath()))->toThrow(ValidationException::class, 'bukan rumus');
    $book->disconnectWorksheets();
});

it('rejects missing uploads and an invalid file type', function () {
    Livewire::test('admin.students.import')->call('previewData')->assertHasErrors('file');
    Livewire::test('admin.students.import')->set('file', UploadedFile::fake()->createWithContent('data.txt', 'nama siswa'))
        ->call('previewData')->assertHasErrors('file');
});

it('enforces a maximum of 1000 students per import', function () {
    expect(fn () => $this->service->validateRows(array_fill(0, 1001, ['full_name' => 'Siswa'])))
        ->toThrow(ValidationException::class);
});
