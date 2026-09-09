<?php

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'active';

    #[Url]
    public string $schoolClassId = '';
    public ?int $academicYearId = null;
    public ?int $semesterId = null;

    public int $perPage = 15;

    public bool $showForm = false;
    public ?int $editingStudentId = null;

    public string $nis = '';
    public string $nisn = '';
    public string $nik = '';
    public string $fullName = '';
    public string $gender = '';
    public string $birthPlace = '';
    public string $birthDate = '';

    public string $fatherName = '';
    public string $motherName = '';
    public string $guardianName = '';
    public string $guardianPhone = '';

    public string $address = '';

    public bool $pipStatus = false;
    public string $pipNumber = '';

    public bool $kipStatus = false;
    public string $kipNumber = '';

    public bool $isActive = true;

    public ?int $studentClassId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSchoolClassId(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->academicYearId = \App\Models\AcademicYear::query()
            ->where('is_active', true)
            ->value('id');

        $this->semesterId = \App\Models\Semester::query()
            ->where('is_active', true)
            ->value('id');
    }
    public function getSchoolClassesProperty(): Collection
    {
        return SchoolClass::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getStudentsProperty()
    {
        return Student::query()
            ->with([
                'classHistories' => function ($query) {
                    $query
                        ->with('schoolClass')
                        ->where('is_active', true)
                        ->latest('id');
                },
            ])
            ->when(
                $this->search !== '',
                function (Builder $query) {
                    $search = '%' . $this->search . '%';

                    $query->where(function (Builder $query) use ($search) {
                        $query
                            ->where('full_name', 'like', $search)
                            ->orWhere('nis', 'like', $search)
                            ->orWhere('nisn', 'like', $search)
                            ->orWhere('nik', 'like', $search);
                    });
                }
            )
            ->when(
                $this->status === 'active',
                fn (Builder $query) =>
                    $query->where('is_active', true)
            )
            ->when(
                $this->status === 'inactive',
                fn (Builder $query) =>
                    $query->where('is_active', false)
            )
            ->when(
                $this->schoolClassId !== '',
                function (Builder $query) {
                    $query->whereHas('classHistories', function (Builder $query) {
                        $query
                            ->where('school_class_id', $this->schoolClassId)
                            ->where('is_active', true);
                    });
                }
            )
            ->orderBy('full_name')
            ->paginate($this->perPage);
    }

    public function deleteStudent(int $studentId): void
    {
        $student = Student::query()->findOrFail($studentId);

        $student->delete();

        session()->flash(
            'success',
            'Data siswa berhasil dihapus.'
        );
    }

    public function createStudent(): void
    {
        $this->resetForm();

        $this->showForm = true;
    }

    public function editStudent(int $studentId): void
    {
        $student = Student::query()
            ->with([
                'classHistories' => fn ($query) => $query
                    ->where('is_active', true)
                    ->latest('id'),
            ])
            ->findOrFail($studentId);

        $this->editingStudentId = $student->id;

        $this->nis = $student->nis ?? '';
        $this->nisn = $student->nisn ?? '';
        $this->nik = $student->nik ?? '';
        $this->fullName = $student->full_name;
        $this->gender = $student->gender ?? '';
        $this->birthPlace = $student->birth_place ?? '';
        $this->birthDate = $student->birth_date?->format('Y-m-d') ?? '';

        $this->fatherName = $student->father_name ?? '';
        $this->motherName = $student->mother_name ?? '';
        $this->guardianName = $student->guardian_name ?? '';
        $this->guardianPhone = $student->guardian_phone ?? '';

        $this->address = $student->address ?? '';

        $this->pipStatus = $student->pip_status;
        $this->pipNumber = $student->pip_number ?? '';

        $this->kipStatus = $student->kip_status;
        $this->kipNumber = $student->kip_number ?? '';

        $this->isActive = $student->is_active;

        $this->studentClassId = $student->classHistories->first()?->school_class_id;

        $this->resetValidation();

        $this->showForm = true;
    }

    public function saveStudent(): void
    {
        $validated = $this->validate([
            'nis' => ['nullable', 'string', 'max:30', 'unique:students,nis,' . $this->editingStudentId],
            'nisn' => ['nullable', 'string', 'max:30', 'unique:students,nisn,' . $this->editingStudentId],
            'nik' => ['nullable', 'string', 'max:30', 'unique:students,nik,' . $this->editingStudentId],

            'fullName' => ['required', 'string', 'max:150'],
            'gender' => ['nullable', 'in:male,female'],
            'birthPlace' => ['nullable', 'string', 'max:100'],
            'birthDate' => ['nullable', 'date'],

            'fatherName' => ['nullable', 'string', 'max:150'],
            'motherName' => ['nullable', 'string', 'max:150'],
            'guardianName' => ['nullable', 'string', 'max:150'],
            'guardianPhone' => ['nullable', 'string', 'max:30'],

            'address' => ['nullable', 'string'],

            'pipStatus' => ['boolean'],
            'pipNumber' => ['nullable', 'string', 'max:50'],

            'kipStatus' => ['boolean'],
            'kipNumber' => ['nullable', 'string', 'max:50'],

            'isActive' => ['boolean'],

            'studentClassId' => ['required', 'integer', 'exists:school_classes,id'],
        ]);

        $student = $this->editingStudentId
            ? Student::query()->findOrFail($this->editingStudentId)
            : new Student();

        if (! $student->exists) {
            $student->student_code = 'S-' . strtoupper(uniqid());
        }

        $student->fill([
            'nis' => $validated['nis'] ?: null,
            'nisn' => $validated['nisn'] ?: null,
            'nik' => $validated['nik'] ?: null,
            'full_name' => $validated['fullName'],
            'gender' => $validated['gender'] ?: null,
            'birth_place' => $validated['birthPlace'] ?: null,
            'birth_date' => $validated['birthDate'] ?: null,

            'father_name' => $validated['fatherName'] ?: null,
            'mother_name' => $validated['motherName'] ?: null,
            'guardian_name' => $validated['guardianName'] ?: null,
            'guardian_phone' => $validated['guardianPhone'] ?: null,

            'address' => $validated['address'] ?: null,

            'pip_status' => $validated['pipStatus'],
            'pip_number' => $validated['pipStatus']
                ? ($validated['pipNumber'] ?: null)
                : null,

            'kip_status' => $validated['kipStatus'],
            'kip_number' => $validated['kipStatus']
                ? ($validated['kipNumber'] ?: null)
                : null,

            'is_active' => $validated['isActive'],
        ]);

        $student->save();

        $student->classHistories()
            ->where('is_active', true)
            ->update([
                'is_active' => false,
            ]);

        $student->classHistories()->create([
            'school_class_id' => $validated['studentClassId'],
            'academic_year_id' => $this->academicYearId,
            'semester_id' => $this->semesterId,
            'is_active' => true,
        ]);

        $this->showForm = false;

        $this->resetForm();

        session()->flash(
            'success',
            $this->editingStudentId
                ? 'Data siswa berhasil diperbarui.'
                : 'Data siswa berhasil ditambahkan.'
        );
    }

    public function closeForm(): void
    {
        $this->showForm = false;

        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingStudentId = null;

        $this->nis = '';
        $this->nisn = '';
        $this->nik = '';
        $this->fullName = '';
        $this->gender = '';
        $this->birthPlace = '';
        $this->birthDate = '';

        $this->fatherName = '';
        $this->motherName = '';
        $this->guardianName = '';
        $this->guardianPhone = '';

        $this->address = '';

        $this->pipStatus = false;
        $this->pipNumber = '';

        $this->kipStatus = false;
        $this->kipNumber = '';

        $this->isActive = true;

        $this->studentClassId = null;

        $this->resetValidation();
    }

    public function render()
    {
        return $this->view();
    }
};
?>

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <flux:heading size="xl">
                Data Pokok Siswa
            </flux:heading>

            <flux:text class="mt-1">
                Kelola data identitas dan data pokok siswa madrasah.
            </flux:text>
        </div>

        <flux:button
            variant="primary"
            icon="plus"
            wire:click="createStudent"
        >
            Tambah Siswa
        </flux:button>

    </div>

    {{-- Flash message --}}
    @if (session()->has('success'))

        <flux:callout
            variant="success"
            icon="check-circle"
        >
            {{ session('success') }}
        </flux:callout>

    @endif

    {{-- Filter --}}
    <flux:card class="p-4">

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

            <flux:input
                label="Cari Siswa"
                placeholder="Nama, NIS, NISN, atau NIK..."
                icon="magnifying-glass"
                wire:model.live.debounce.300ms="search"
            />

            <flux:select
                label="Status"
                wire:model.live="status"
            >
                <option value="active">
                    Aktif
                </option>

                <option value="inactive">
                    Tidak Aktif
                </option>

                <option value="all">
                    Semua
                </option>
            </flux:select>

            <flux:select
                label="Kelas"
                wire:model.live="schoolClassId"
            >
                <option value="">
                    Semua Kelas
                </option>

                @foreach ($this->schoolClasses as $schoolClass)
                    <option value="{{ $schoolClass->id }}">
                        {{ $schoolClass->name }}
                    </option>
                @endforeach
            </flux:select>

        </div>

    </flux:card>

    {{-- Table --}}
    <flux:card class="overflow-hidden">

        <div class="overflow-x-auto">

            <table class="min-w-full text-sm">

                <thead>
                    <tr class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800">

                        <th class="px-4 py-3 text-left font-semibold">
                            Siswa
                        </th>

                        <th class="px-4 py-3 text-left font-semibold">
                            NIS / NISN
                        </th>

                        <th class="px-4 py-3 text-left font-semibold">
                            Kelas
                        </th>

                        <th class="px-4 py-3 text-left font-semibold">
                            Jenis Kelamin
                        </th>

                        <th class="px-4 py-3 text-left font-semibold">
                            Status
                        </th>

                        <th class="w-24 px-4 py-3 text-right font-semibold">
                            Aksi
                        </th>

                    </tr>
                </thead>

                <tbody>

                    @forelse ($this->students as $student)

                        @php
                            $activeHistory = $student->classHistories->first();
                        @endphp

                        <tr
                            wire:key="student-{{ $student->id }}"
                            class="border-b border-zinc-100 last:border-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/50"
                        >

                            <td class="px-4 py-3">

                                <div class="font-medium">
                                    {{ $student->full_name }}
                                </div>

                                @if ($student->nik)
                                    <div class="mt-0.5 text-xs text-zinc-500">
                                        NIK: {{ $student->nik }}
                                    </div>
                                @endif

                            </td>

                            <td class="px-4 py-3">

                                <div>
                                    {{ $student->nis ?: '-' }}
                                </div>

                                <div class="mt-0.5 text-xs text-zinc-500">
                                    NISN: {{ $student->nisn ?: '-' }}
                                </div>

                            </td>

                            <td class="px-4 py-3">

                                @if ($activeHistory?->schoolClass)

                                    <flux:badge color="zinc">
                                        {{ $activeHistory->schoolClass->name }}
                                    </flux:badge>

                                @else

                                    <span class="text-xs text-zinc-400">
                                        Belum ditempatkan
                                    </span>

                                @endif

                            </td>

                            <td class="px-4 py-3">

                                @if ($student->gender === 'male')
                                    Laki-laki
                                @elseif ($student->gender === 'female')
                                    Perempuan
                                @else
                                    -
                                @endif

                            </td>

                            <td class="px-4 py-3">

                                @if ($student->is_active)

                                    <flux:badge color="green">
                                        Aktif
                                    </flux:badge>

                                @else

                                    <flux:badge color="zinc">
                                        Tidak Aktif
                                    </flux:badge>

                                @endif

                            </td>

                            <td class="px-4 py-3">

                                <div class="flex justify-end gap-1">

                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="pencil"
                                        wire:click="editStudent({{ $student->id }})"
                                        title="Edit"
                                    />

                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        icon="trash"
                                        class="text-red-600 hover:text-red-700"
                                        wire:click="deleteStudent({{ $student->id }})"
                                        wire:confirm="Yakin ingin menghapus data siswa {{ $student->full_name }}?"
                                        title="Hapus"
                                    />

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="6"
                                class="px-4 py-12 text-center"
                            >
                                <flux:icon
                                    name="academic-cap"
                                    class="mx-auto size-10 text-zinc-400"
                                />

                                <flux:heading size="lg" class="mt-3">
                                    Belum ada data siswa
                                </flux:heading>

                                <flux:text class="mt-1">
                                    Tidak ada siswa yang sesuai dengan filter.
                                </flux:text>
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if ($this->students->hasPages())

            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">
                {{ $this->students->links() }}
            </div>

        @endif

    </flux:card>

    <flux:modal
    wire:model="showForm"
    class="md:w-[700px]"
>
    <div class="space-y-6">

        <div>
            <flux:heading size="lg">
                {{ $editingStudentId ? 'Edit Data Siswa' : 'Tambah Siswa' }}
            </flux:heading>

            <flux:text class="mt-1">
                Lengkapi data pokok siswa.
            </flux:text>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

            <flux:input
                label="NIS"
                wire:model="nis"
            />

            <flux:input
                label="NISN"
                wire:model="nisn"
            />

            <flux:input
                label="NIK"
                wire:model="nik"
            />

            <flux:select
                label="Jenis Kelamin"
                wire:model="gender"
            >
                <option value="">Pilih jenis kelamin</option>
                <option value="male">Laki-laki</option>
                <option value="female">Perempuan</option>
            </flux:select>

            <flux:input
                label="Nama Lengkap"
                wire:model="fullName"
                class="sm:col-span-2"
            />

            <flux:input
                label="Tempat Lahir"
                wire:model="birthPlace"
            />

            <flux:input
                type="date"
                label="Tanggal Lahir"
                wire:model="birthDate"
            />

            <flux:select
                label="Kelas"
                wire:model="studentClassId"
                class="sm:col-span-2"
            >
                <option value="">Pilih kelas</option>

                @foreach ($this->schoolClasses as $schoolClass)
                    <option value="{{ $schoolClass->id }}">
                        {{ $schoolClass->name }}
                    </option>
                @endforeach
            </flux:select>

        </div>

        <div class="border-t border-zinc-200 pt-5 dark:border-zinc-700">

            <flux:heading size="sm">
                Data Orang Tua / Wali
            </flux:heading>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">

                <flux:input
                    label="Nama Ayah"
                    wire:model="fatherName"
                />

                <flux:input
                    label="Nama Ibu"
                    wire:model="motherName"
                />

                <flux:input
                    label="Nama Wali"
                    wire:model="guardianName"
                />

                <flux:input
                    label="No. HP Wali"
                    wire:model="guardianPhone"
                />

                <flux:textarea
                    label="Alamat"
                    wire:model="address"
                    class="sm:col-span-2"
                />

            </div>

        </div>

        <div class="border-t border-zinc-200 pt-5 dark:border-zinc-700">

            <flux:heading size="sm">
                Bantuan Pendidikan
            </flux:heading>

            <div class="mt-4 space-y-4">

                <flux:checkbox
                    label="Penerima PIP"
                    wire:model.live="pipStatus"
                />

                @if ($pipStatus)
                    <flux:input
                        label="Nomor PIP"
                        wire:model="pipNumber"
                    />
                @endif

                <flux:checkbox
                    label="Memiliki KIP"
                    wire:model.live="kipStatus"
                />

                @if ($kipStatus)
                    <flux:input
                        label="Nomor KIP"
                        wire:model="kipNumber"
                    />
                @endif

            </div>

        </div>

        <div class="border-t border-zinc-200 pt-5 dark:border-zinc-700">

            <flux:checkbox
                label="Siswa Aktif"
                wire:model="isActive"
            />

        </div>

        <div class="flex justify-end gap-2">

            <flux:button
                variant="ghost"
                wire:click="closeForm"
            >
                Batal
            </flux:button>

            <flux:button
                variant="primary"
                wire:click="saveStudent"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="saveStudent">
                    {{ $editingStudentId ? 'Simpan Perubahan' : 'Simpan Siswa' }}
                </span>

                <span wire:loading wire:target="saveStudent">
                    Menyimpan...
                </span>
            </flux:button>

        </div>

    </div>
</flux:modal>
</div>
