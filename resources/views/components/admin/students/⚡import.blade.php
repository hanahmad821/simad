<?php

use App\Imports\StudentsImport;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

new class extends Component {
    use WithFileUploads;

    public $file;

    public ?int $academicYearId = null;
    public ?int $semesterId = null;
    public ?int $schoolClassId = null;

    public array $preview = [];

    public bool $showPreview = false;

    public function mount(): void
    {
        $this->academicYearId = AcademicYear::query()
            ->where('is_active', true)
            ->value('id');

        $this->semesterId = Semester::query()
            ->where('is_active', true)
            ->value('id');
    }

    public function previewData(): void
    {
        $this->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:5120',
            ],
            'academicYearId' => ['required', 'exists:academic_years,id'],
            'semesterId' => ['required', 'exists:semesters,id'],
            'schoolClassId' => ['required', 'exists:school_classes,id'],
        ]);

        $import = new StudentsImport();

        Excel::import($import, $this->file->getRealPath());

        $this->preview = $import->rows
            ->map(fn($row) => $row->toArray())
            ->values()
            ->toArray();

        $this->showPreview = true;
    }

    public function import(): void
    {
        $this->validate([
            'academicYearId' => ['required', 'exists:academic_years,id'],
            'semesterId' => ['required', 'exists:semesters,id'],
            'schoolClassId' => ['required', 'exists:school_classes,id'],
        ]);

        if (empty($this->preview)) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->preview as $row) {
                $student = \App\Models\Student::create([
                    'student_code' => 'S-' . strtoupper(uniqid()),
                    'nis' => $this->nullIfEmpty($row['nis'] ?? null),
                    'nisn' => $this->nullIfEmpty($row['nisn'] ?? null),
                    'nik' => $this->normalizeNik($row['nik'] ?? null),
                    'full_name' => trim($row['full_name'] ?? ''),
                    'gender' => $this->normalizeGender($row['gender'] ?? null),
                    'birth_place' => $this->nullIfEmpty($row['birth_place'] ?? null),
                    'birth_date' => $this->nullIfEmpty($row['birth_date'] ?? null),
                    'father_name' => $this->nullIfEmpty($row['father_name'] ?? null),
                    'mother_name' => $this->nullIfEmpty($row['mother_name'] ?? null),
                    'guardian_name' => $this->nullIfEmpty($row['guardian_name'] ?? null),
                    'guardian_phone' => $this->nullIfEmpty($row['guardian_phone'] ?? null),
                    'address' => $this->nullIfEmpty($row['address'] ?? null),
                    'pip_status' => $this->toBool($row['pip_status'] ?? false),
                    'pip_number' => $this->nullIfEmpty($row['pip_number'] ?? null),
                    'kip_status' => $this->toBool($row['kip_status'] ?? false),
                    'kip_number' => $this->nullIfEmpty($row['kip_number'] ?? null),
                    'is_active' => $this->toBool($row['is_active'] ?? true),
                ]);

                $student->classHistories()->create([
                    'school_class_id' => $this->schoolClassId,
                    'academic_year_id' => $this->academicYearId,
                    'semester_id' => $this->semesterId,
                    'is_active' => true,
                ]);
            }
        });

        $count = count($this->preview);

        $this->reset([
            'file',
            'preview',
            'showPreview',
        ]);

        session()->flash(
            'success',
            "{$count} data siswa berhasil diimport."
        );
    }

    private function nullIfEmpty($value): mixed
    {
        return blank($value) ? null : $value;
    }

    private function toBool($value): bool
    {
        return in_array(
            strtolower(trim((string) $value)),
            ['1', 'true', 'yes', 'ya'],
            true
        );
    }

    private function normalizeGender($value): ?string
    {
        return match (strtolower(trim((string) $value))) {
            'male', 'l', 'laki-laki', 'laki laki' => 'male',
            'female', 'p', 'perempuan' => 'female',
            default => null,
        };
    }
    private function normalizeNik($value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return preg_replace('/\D/', '', trim((string) $value));
    }

    public function getAcademicYearsProperty()
    {
        return AcademicYear::query()
            ->orderByDesc('id')
            ->get();
    }

    public function getSemestersProperty()
    {
        return Semester::query()
            ->orderBy('id')
            ->get();
    }

    public function getSchoolClassesProperty()
    {
        return SchoolClass::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function downloadTemplate()
{
    return Excel::download(
        new \App\Exports\StudentsTemplateExport(),
        'template-data-siswa.xlsx'
    );
}

    // public function downloadTemplate()
    // {
    //     return response()->streamDownload(function () {
    //         $handle = fopen('php://output', 'w');

    //         fputcsv($handle, [
    //             'nis',
    //             'nisn',
    //             'nik',
    //             'full_name',
    //             'gender',
    //             'birth_place',
    //             'birth_date',
    //             'father_name',
    //             'mother_name',
    //             'guardian_name',
    //             'guardian_phone',
    //             'address',
    //             'pip_status',
    //             'pip_number',
    //             'kip_status',
    //             'kip_number',
    //             'is_active',
    //         ]);

    //         fputcsv($handle, [
    //             '12345',
    //             '0012345678',
    //             '3302xxxxxxxxxxxx',
    //             'Nama Siswa',
    //             'male',
    //             'Purwokerto',
    //             '2010-01-15',
    //             'Nama Ayah',
    //             'Nama Ibu',
    //             '',
    //             '',
    //             'Alamat siswa',
    //             '0',
    //             '',
    //             '0',
    //             '',
    //             '1',
    //         ]);

    //         fclose($handle);
    //     }, 'template-data-siswa.csv');
    // }

    public function render()
    {
        return $this->view();
    }
};
?>

<div class="space-y-6">

<div class="flex items-center justify-between">
    <div>
        <flux:heading size="xl">
            Import Data Siswa
        </flux:heading>

        <flux:text class="mt-1">
            Import data siswa dari file Excel.
        </flux:text>
    </div>

    <flux:button
        variant="ghost"
        icon="arrow-down-tray"
        wire:click="downloadTemplate"
    >
        Download Template
    </flux:button>
</div>

    @if (session('success'))
        <flux:callout variant="success">
            {{ session('success') }}
        </flux:callout>
    @endif

    <flux:card class="space-y-5">

        <div class="grid gap-4 md:grid-cols-3">

            <flux:select wire:model="academicYearId" label="Tahun Pelajaran">
                <option value="">Pilih tahun pelajaran</option>

                @foreach ($this->academicYears as $year)
                    <option value="{{ $year->id }}">
                        {{ $year->name }}
                    </option>
                @endforeach
            </flux:select>

            <flux:select wire:model="semesterId" label="Semester">
                <option value="">Pilih semester</option>

                @foreach ($this->semesters as $semester)
                    <option value="{{ $semester->id }}">
                        {{ $semester->name }}
                    </option>
                @endforeach
            </flux:select>

            <flux:select wire:model="schoolClassId" label="Kelas">
                <option value="">Pilih kelas</option>

                @foreach ($this->schoolClasses as $class)
                    <option value="{{ $class->id }}">
                        {{ $class->name }}
                    </option>
                @endforeach
            </flux:select>

        </div>

        <div>
            <flux:input type="file" wire:model="file" label="File Excel" accept=".xlsx,.xls,.csv" />

            <flux:text size="sm" class="mt-1">
                Maksimal 5 MB. Gunakan template yang disediakan.
            </flux:text>
        </div>

        <div class="flex justify-end">
            <flux:button variant="primary" wire:click="previewData" wire:loading.attr="disabled">
                Preview Data
            </flux:button>
        </div>

    </flux:card>

    @if ($showPreview)

        <flux:card>

            <div class="mb-4 flex items-center justify-between">
                <div>
                    <flux:heading size="lg">
                        Preview Data
                    </flux:heading>

                    <flux:text>
                        {{ count($preview) }} data siap diimport.
                    </flux:text>
                </div>

                <flux:button variant="primary" wire:click="import" wire:confirm="Yakin ingin mengimport data siswa ini?">
                    Import Sekarang
                </flux:button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="px-3 py-2">No</th>
                            <th class="px-3 py-2">NIS</th>
                            <th class="px-3 py-2">NISN</th>
                            <th class="px-3 py-2">NIK</th>
                            <th class="px-3 py-2">Nama</th>
                            <th class="px-3 py-2">Gender</th>
                            <th class="px-3 py-2">Tempat Lahir</th>
                            <th class="px-3 py-2">Tanggal Lahir</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($preview as $index => $row)
                            <tr class="border-b">
                                <td class="px-3 py-2">
                                    {{ $index + 1 }}
                                </td>

                                <td class="px-3 py-2">
                                    {{ $row['nis'] ?? '-' }}
                                </td>

                                <td class="px-3 py-2">
                                    {{ $row['nisn'] ?? '-' }}
                                </td>

                                <td class="px-3 py-2">
                                    {{ $row['nik'] ?? '-' }}
                                </td>

                                <td class="px-3 py-2 font-medium">
                                    {{ $row['full_name'] ?? '-' }}
                                </td>

                                <td class="px-3 py-2">
                                    {{ $row['gender'] ?? '-' }}
                                </td>

                                <td class="px-3 py-2">
                                    {{ $row['birth_place'] ?? '-' }}
                                </td>

                                <td class="px-3 py-2">
                                    {{ $row['birth_date'] ?? '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </flux:card>

    @endif

</div>
