<?php

use App\Models\Gtk;
use App\Models\GtkEducation;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Livewire\Component;

new class extends Component {
    public Gtk $gtk;

    public bool $showEducationForm = false;

    public ?int $editingEducationId = null;

    public string $educationLevel = '';
    public string $institutionName = '';
    public string $major = '';
    public string $startYear = '';
    public string $graduationYear = '';
    public string $degree = '';

    public bool $showAssignmentForm = false;

    public ?int $editingAssignmentId = null;

    public ?int $subjectId = null;
    public ?int $schoolClassId = null;
    public ?int $academicYearId = null;
    public ?int $semesterId = null;

    public int|string|null $teachingHours = null;

    public bool $assignmentIsActive = true;

    public function mount(Gtk $gtk): void
    {
        $this->gtk = $gtk->load([
            'educations',
            'teachingAssignments.subject',
            'teachingAssignments.schoolClass',
            'teachingAssignments.academicYear',
            'teachingAssignments.semester',
        ]);
    }

    public function createEducation(): void
    {
        $this->resetEducationForm();

        $this->showEducationForm = true;
    }

    public function editEducation(int $id): void
    {
        $education = $this->gtk->educations()
            ->findOrFail($id);

        $this->editingEducationId = $education->id;

        $this->educationLevel = $education->level ?? '';
        $this->institutionName = $education->institution_name ?? '';
        $this->major = $education->major ?? '';
        $this->startYear = $education->start_year
            ? (string) $education->start_year
            : '';
        $this->graduationYear = $education->graduation_year
            ? (string) $education->graduation_year
            : '';
        $this->degree = $education->degree ?? '';

        $this->resetValidation();

        $this->showEducationForm = true;
    }

    public function saveEducation(): void
    {
        $validated = $this->validate([
            'educationLevel' => [
                'required',
                'string',
                'max:50',
            ],

            'institutionName' => [
                'required',
                'string',
                'max:150',
            ],

            'major' => [
                'nullable',
                'string',
                'max:150',
            ],

            'startYear' => [
                'nullable',
                'integer',
                'min:1900',
                'max:' . now()->year,
            ],

            'graduationYear' => [
                'nullable',
                'integer',
                'min:1900',
                'max:' . now()->year,
            ],

            'degree' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        if ($this->editingEducationId) {

            $education = $this->gtk->educations()
                ->findOrFail($this->editingEducationId);

            $education->update([
                'level' => $validated['educationLevel'],
                'institution_name' => $validated['institutionName'],
                'major' => $validated['major'],
                'start_year' => $validated['startYear'] ?: null,
                'graduation_year' => $validated['graduationYear'] ?: null,
                'degree' => $validated['degree'],
            ]);

            session()->flash(
                'success',
                'Riwayat pendidikan berhasil diperbarui.'
            );

        } else {

            $this->gtk->educations()->create([
                'level' => $validated['educationLevel'],
                'institution_name' => $validated['institutionName'],
                'major' => $validated['major'],
                'start_year' => $validated['startYear'] ?: null,
                'graduation_year' => $validated['graduationYear'] ?: null,
                'degree' => $validated['degree'],
            ]);

            session()->flash(
                'success',
                'Riwayat pendidikan berhasil ditambahkan.'
            );
        }

        $this->gtk->load('educations');

        $this->closeEducationForm();
    }

    public function deleteEducation(int $id): void
    {
        $education = $this->gtk->educations()
            ->findOrFail($id);

        $education->delete();

        $this->gtk->load('educations');

        session()->flash(
            'success',
            'Riwayat pendidikan berhasil dihapus.'
        );
    }

    public function closeEducationForm(): void
    {
        $this->showEducationForm = false;

        $this->resetEducationForm();
    }

    private function resetEducationForm(): void
    {
        $this->reset([
            'editingEducationId',
            'educationLevel',
            'institutionName',
            'major',
            'startYear',
            'graduationYear',
            'degree',
        ]);

        $this->resetValidation();
    }

    public function createAssignment(): void
    {
        $this->resetAssignmentForm();

        $this->showAssignmentForm = true;
    }

    public function editAssignment(int $id): void
    {
        $assignment = $this->gtk->teachingAssignments()
            ->findOrFail($id);

        $this->editingAssignmentId = $assignment->id;

        $this->subjectId = $assignment->subject_id;
        $this->schoolClassId = $assignment->school_class_id;
        $this->academicYearId = $assignment->academic_year_id;
        $this->semesterId = $assignment->semester_id;
        $this->teachingHours = $assignment->teaching_hours;
        $this->assignmentIsActive = (bool) $assignment->is_active;

        $this->resetValidation();

        $this->showAssignmentForm = true;
    }

    public function saveAssignment(): void
    {
        $validated = $this->validate([
            'subjectId' => [
                'required',
                'exists:subjects,id',
            ],

            'schoolClassId' => [
                'required',
                'exists:school_classes,id',
            ],

            'academicYearId' => [
                'required',
                'exists:academic_years,id',
            ],

            'semesterId' => [
                'required',
                'exists:semesters,id',
            ],

            'teachingHours' => [
                'required',
                'integer',
                'min:1',
                'max:50',
            ],

            'assignmentIsActive' => [
                'boolean',
            ],
        ]);

        $exists = TeachingAssignment::query()
            ->where('subject_id', $validated['subjectId'])
            ->where('school_class_id', $validated['schoolClassId'])
            ->where('academic_year_id', $validated['academicYearId'])
            ->where('semester_id', $validated['semesterId'])
            ->when(
                $this->editingAssignmentId,
                function ($query) {
                    $query->where('id', '!=', $this->editingAssignmentId);
                }
            )
            ->exists();

        if ($exists) {
            $this->addError(
                'subjectId',
                'Mata pelajaran tersebut sudah memiliki guru pengampu pada kelas, tahun pelajaran, dan semester yang dipilih.'
            );

            return;
        }

        $data = [
            'subject_id' => $validated['subjectId'],
            'school_class_id' => $validated['schoolClassId'],
            'academic_year_id' => $validated['academicYearId'],
            'semester_id' => $validated['semesterId'],
            'teaching_hours' => $validated['teachingHours'],
            'is_active' => $validated['assignmentIsActive'],
        ];

        if ($this->editingAssignmentId) {

            $assignment = $this->gtk->teachingAssignments()
                ->findOrFail($this->editingAssignmentId);

            $assignment->update($data);

            session()->flash(
                'success',
                'Penugasan mengajar berhasil diperbarui.'
            );

        } else {

            $this->gtk->teachingAssignments()->create($data);

            session()->flash(
                'success',
                'Penugasan mengajar berhasil ditambahkan.'
            );
        }

        $this->gtk->load([
            'teachingAssignments.subject',
            'teachingAssignments.schoolClass',
            'teachingAssignments.academicYear',
            'teachingAssignments.semester',
        ]);

        $this->closeAssignmentForm();
    }

    public function deleteAssignment(int $id): void
    {
        $assignment = $this->gtk->teachingAssignments()
            ->findOrFail($id);

        $assignment->delete();

        $this->gtk->load([
            'teachingAssignments.subject',
            'teachingAssignments.schoolClass',
            'teachingAssignments.academicYear',
            'teachingAssignments.semester',
        ]);

        session()->flash(
            'success',
            'Penugasan mengajar berhasil dihapus.'
        );
    }

    public function closeAssignmentForm(): void
    {
        $this->showAssignmentForm = false;

        $this->resetAssignmentForm();
    }

    private function resetAssignmentForm(): void
    {
        $this->reset([
            'editingAssignmentId',
            'subjectId',
            'schoolClassId',
            'academicYearId',
            'semesterId',
            'teachingHours',
        ]);

        $this->assignmentIsActive = true;

        $this->resetValidation();
    }

    public function with(): array
    {
        return [
            'subjects' => Subject::query()
                ->orderBy('name')
                ->get(),

            'schoolClasses' => SchoolClass::query()
                ->orderBy('name')
                ->get(),

            'academicYears' => AcademicYear::query()
                ->orderByDesc('id')
                ->get(),

            'semesters' => Semester::query()
                ->orderBy('id')
                ->get(),
        ];
    }
};


?>

<div class="flex flex-col gap-6">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-4">

        <div>
            <flux:heading size="xl">
                Detail GTK
            </flux:heading>

            <flux:text class="mt-1">
                Informasi lengkap Guru dan Tenaga Kependidikan.
            </flux:text>
        </div>

        <div class="flex gap-2">

            <flux:button :href="route('admin.gtks.index')" wire:navigate icon="arrow-left">
                Kembali
            </flux:button>

        </div>

    </div>


    {{-- Profil --}}
    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Identitas --}}
        <flux:card class="lg:col-span-2">

            <div class="mb-6">
                <flux:heading size="lg">
                    Identitas GTK
                </flux:heading>

                <flux:text class="mt-1">
                    Informasi identitas dan data pribadi.
                </flux:text>
            </div>

            <div class="grid gap-5 md:grid-cols-2">

                <div>
                    <flux:text size="sm">
                        Nama Lengkap
                    </flux:text>

                    <div class="mt-1 font-medium">
                        {{ $gtk->full_name }}
                    </div>
                </div>

                <div>
                    <flux:text size="sm">
                        Jenis Kelamin
                    </flux:text>

                    <div class="mt-1">
                        @if ($gtk->gender === 'L')
                            Laki-laki
                        @elseif ($gtk->gender === 'P')
                            Perempuan
                        @else
                            -
                        @endif
                    </div>
                </div>

                <div>
                    <flux:text size="sm">
                        Tempat, Tanggal Lahir
                    </flux:text>

                    <div class="mt-1">
                        {{ $gtk->birth_place ?: '-' }}

                        @if ($gtk->birth_date)
                            , {{ $gtk->birth_date->translatedFormat('d F Y') }}
                        @endif
                    </div>
                </div>

                <div>
                    <flux:text size="sm">
                        Nomor HP
                    </flux:text>

                    <div class="mt-1">
                        {{ $gtk->phone ?: '-' }}
                    </div>
                </div>

                <div>
                    <flux:text size="sm">
                        Email
                    </flux:text>

                    <div class="mt-1">
                        {{ $gtk->email ?: '-' }}
                    </div>
                </div>

                <div class="md:col-span-2">
                    <flux:text size="sm">
                        Alamat
                    </flux:text>

                    <div class="mt-1">
                        {{ $gtk->address ?: '-' }}
                    </div>
                </div>

            </div>

        </flux:card>


        {{-- Status --}}
        <flux:card>

            <flux:heading size="lg">
                Status Kepegawaian
            </flux:heading>

            <div class="mt-5 space-y-5">

                <div>
                    <flux:text size="sm">
                        Status
                    </flux:text>

                    <div class="mt-1">
                        {{ $gtk->employment_status ?: '-' }}
                    </div>
                </div>

                <div>
                    <flux:text size="sm">
                        Jabatan
                    </flux:text>

                    <div class="mt-1">
                        {{ $gtk->position ?: '-' }}
                    </div>
                </div>

                <div>
                    <flux:text size="sm">
                        NIP
                    </flux:text>

                    <div class="mt-1">
                        {{ $gtk->nip ?: '-' }}
                    </div>
                </div>

                <div>
                    <flux:text size="sm">
                        NUPTK
                    </flux:text>

                    <div class="mt-1">
                        {{ $gtk->nuptk ?: '-' }}
                    </div>
                </div>

                <div>
                    <flux:text size="sm">
                        Nomor Pegawai
                    </flux:text>

                    <div class="mt-1">
                        {{ $gtk->employee_number ?: '-' }}
                    </div>
                </div>

                <div>
                    <flux:text size="sm">
                        Status Aktif
                    </flux:text>

                    <div class="mt-2">
                        @if ($gtk->is_active)
                            <flux:badge color="green">
                                Aktif
                            </flux:badge>
                        @else
                            <flux:badge color="zinc">
                                Tidak Aktif
                            </flux:badge>
                        @endif
                    </div>
                </div>

            </div>

        </flux:card>

    </div>


    {{-- Riwayat Pendidikan --}}
    <flux:card>

        <div class="mb-5 flex items-center justify-between gap-4">

            <div>
                <flux:heading size="lg">
                    Riwayat Pendidikan
                </flux:heading>

                <flux:text class="mt-1">
                    Riwayat pendidikan formal GTK.
                </flux:text>
            </div>

            <flux:button variant="primary" icon="plus" wire:click="createEducation">
                Tambah Pendidikan
            </flux:button>

        </div>


        @if ($gtk->educations->isEmpty())

            <div class="rounded-lg border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">

                <flux:text>
                    Belum ada riwayat pendidikan.
                </flux:text>

            </div>

        @else

            <div class="overflow-x-auto">

                <table class="w-full text-sm">

                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">

                            <th class="px-4 py-3 text-left">
                                Jenjang
                            </th>

                            <th class="px-4 py-3 text-left">
                                Institusi
                            </th>

                            <th class="px-4 py-3 text-left">
                                Program Studi
                            </th>

                            <th class="px-4 py-3 text-left">
                                Tahun
                            </th>

                            <th class="px-4 py-3 text-left">
                                Gelar
                            </th>

                            <th class="px-4 py-3 text-right">
                                Aksi
                            </th>

                        </tr>
                    </thead>

                    <tbody>

                        @foreach ($gtk->educations as $education)

                            <tr wire:key="education-{{ $education->id }}" class="border-b border-zinc-100 dark:border-zinc-800">

                                <td class="px-4 py-3 font-medium">
                                    {{ $education->level }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $education->institution_name }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $education->major ?: '-' }}
                                </td>

                                <td class="px-4 py-3">

                                    @if ($education->start_year || $education->graduation_year)

                                        {{ $education->start_year ?: '?' }}
                                        -
                                        {{ $education->graduation_year ?: 'Sekarang' }}

                                    @else
                                        -
                                    @endif

                                </td>

                                <td class="px-4 py-3">
                                    {{ $education->degree ?: '-' }}
                                </td>

                                <td class="px-4 py-3">

                                    <div class="flex justify-end gap-2">

                                        <flux:button size="sm" icon="pencil-square"
                                            wire:click="editEducation({{ $education->id }})">
                                            Edit
                                        </flux:button>

                                        <flux:button size="sm" variant="danger" icon="trash"
                                            wire:click="deleteEducation({{ $education->id }})"
                                            wire:confirm="Yakin ingin menghapus riwayat pendidikan ini?">
                                            Hapus
                                        </flux:button>

                                    </div>

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        @endif

    </flux:card>

    <flux:modal wire:model="showEducationForm" class="md:w-[650px]">

        <div class="space-y-6">

            <div>
                <flux:heading size="lg">
                    {{ $editingEducationId
    ? 'Edit Riwayat Pendidikan'
    : 'Tambah Riwayat Pendidikan'
                }}
                </flux:heading>

                <flux:text class="mt-1">
                    Masukkan informasi pendidikan GTK.
                </flux:text>
            </div>


            <div class="space-y-4">

                <flux:select label="Jenjang Pendidikan" wire:model="educationLevel">

                    <flux:select.option value="">
                        Pilih jenjang
                    </flux:select.option>

                    <flux:select.option value="SD/MI">
                        SD / MI
                    </flux:select.option>

                    <flux:select.option value="SMP/MTs">
                        SMP / MTs
                    </flux:select.option>

                    <flux:select.option value="SMA/MA">
                        SMA / MA
                    </flux:select.option>

                    <flux:select.option value="D1">
                        D1
                    </flux:select.option>

                    <flux:select.option value="D2">
                        D2
                    </flux:select.option>

                    <flux:select.option value="D3">
                        D3
                    </flux:select.option>

                    <flux:select.option value="D4">
                        D4
                    </flux:select.option>

                    <flux:select.option value="S1">
                        S1
                    </flux:select.option>

                    <flux:select.option value="S2">
                        S2
                    </flux:select.option>

                    <flux:select.option value="S3">
                        S3
                    </flux:select.option>

                </flux:select>


                <flux:input label="Nama Institusi" wire:model="institutionName"
                    placeholder="Contoh: UIN Prof. K.H. Saifuddin Zuhri Purwokerto" />


                <flux:input label="Program Studi / Jurusan" wire:model="major"
                    placeholder="Contoh: Pendidikan Bahasa Inggris" />


                <div class="grid gap-4 md:grid-cols-2">

                    <flux:input type="number" label="Tahun Mulai" wire:model="startYear" placeholder="Contoh: 2020" />

                    <flux:input type="number" label="Tahun Lulus" wire:model="graduationYear"
                        placeholder="Contoh: 2024" />

                </div>


                <flux:input label="Gelar" wire:model="degree" placeholder="Contoh: S.Pd." />

            </div>


            <div class="flex justify-end gap-2">

                <flux:button variant="ghost" wire:click="closeEducationForm">
                    Batal
                </flux:button>

                <flux:button variant="primary" wire:click="saveEducation">
                    {{ $editingEducationId
    ? 'Simpan Perubahan'
    : 'Simpan'
                }}
                </flux:button>

            </div>

        </div>

    </flux:modal>

    {{-- Penugasan Mengajar --}}
    <flux:card>

        <div class="mb-5 flex items-center justify-between gap-4">

            <div>
                <flux:heading size="lg">
                    Penugasan Mengajar
                </flux:heading>

                <flux:text class="mt-1">
                    Daftar mata pelajaran dan kelas yang diampu GTK.
                </flux:text>
            </div>

            <flux:button variant="primary" icon="plus" wire:click="createAssignment">
                Tambah Penugasan
            </flux:button>

        </div>


        @if ($gtk->teachingAssignments->isEmpty())

            <div class="rounded-lg border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">

                <flux:text>
                    Belum ada penugasan mengajar.
                </flux:text>

            </div>

        @else

            <div class="overflow-x-auto">

                <table class="w-full text-sm">

                    <thead>

                        <tr class="border-b border-zinc-200 dark:border-zinc-700">

                            <th class="px-4 py-3 text-left">
                                Mata Pelajaran
                            </th>

                            <th class="px-4 py-3 text-left">
                                Kelas
                            </th>

                            <th class="px-4 py-3 text-left">
                                Tahun Pelajaran
                            </th>

                            <th class="px-4 py-3 text-left">
                                Semester
                            </th>

                            <th class="px-4 py-3 text-center">
                                Jam
                            </th>

                            <th class="px-4 py-3 text-center">
                                Status
                            </th>

                            <th class="px-4 py-3 text-right">
                                Aksi
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @foreach ($gtk->teachingAssignments as $assignment)

                            <tr wire:key="assignment-{{ $assignment->id }}"
                                class="border-b border-zinc-100 dark:border-zinc-800">

                                <td class="px-4 py-3 font-medium">
                                    {{ $assignment->subject?->name ?? '-' }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $assignment->schoolClass?->name ?? '-' }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $assignment->academicYear?->name ?? '-' }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $assignment->semester?->name ?? '-' }}
                                </td>

                                <td class="px-4 py-3 text-center">
                                    {{ $assignment->teaching_hours }} JP
                                </td>

                                <td class="px-4 py-3 text-center">

                                    @if ($assignment->is_active)

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

                                    <div class="flex justify-end gap-2">

                                        <flux:button size="sm" icon="pencil-square"
                                            wire:click="editAssignment({{ $assignment->id }})">
                                            Edit
                                        </flux:button>

                                        <flux:button size="sm" variant="danger" icon="trash"
                                            wire:click="deleteAssignment({{ $assignment->id }})"
                                            wire:confirm="Yakin ingin menghapus penugasan ini?">
                                            Hapus
                                        </flux:button>

                                    </div>

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        @endif

    </flux:card>

    <flux:modal wire:model="showAssignmentForm" class="md:w-[650px]">

        <div class="space-y-6">

            <div>
                <flux:heading size="lg">
                    {{ $editingAssignmentId
    ? 'Edit Penugasan Mengajar'
    : 'Tambah Penugasan Mengajar'
                }}
                </flux:heading>

                <flux:text class="mt-1">
                    Tentukan mata pelajaran, kelas, dan beban mengajar GTK.
                </flux:text>
            </div>


            <div class="space-y-4">

                {{-- Mata Pelajaran --}}
                <flux:select label="Mata Pelajaran" wire:model="subjectId">

                    <flux:select.option value="">
                        Pilih mata pelajaran
                    </flux:select.option>

                    @foreach ($subjects as $subject)

                        <flux:select.option value="{{ $subject->id }}">
                            {{ $subject->name }}
                        </flux:select.option>

                    @endforeach

                </flux:select>


                {{-- Kelas --}}
                <flux:select label="Kelas" wire:model="schoolClassId">

                    <flux:select.option value="">
                        Pilih kelas
                    </flux:select.option>

                    @foreach ($schoolClasses as $schoolClass)

                        <flux:select.option value="{{ $schoolClass->id }}">
                            {{ $schoolClass->name }}
                        </flux:select.option>

                    @endforeach

                </flux:select>


                <div class="grid gap-4 md:grid-cols-2">

                    {{-- Tahun Pelajaran --}}
                    <flux:select label="Tahun Pelajaran" wire:model="academicYearId">

                        <flux:select.option value="">
                            Pilih tahun pelajaran
                        </flux:select.option>

                        @foreach ($academicYears as $academicYear)

                            <flux:select.option value="{{ $academicYear->id }}">
                                {{ $academicYear->name }}
                            </flux:select.option>

                        @endforeach

                    </flux:select>


                    {{-- Semester --}}
                    <flux:select label="Semester" wire:model="semesterId">

                        <flux:select.option value="">
                            Pilih semester
                        </flux:select.option>

                        @foreach ($semesters as $semester)

                            <flux:select.option value="{{ $semester->id }}">
                                {{ $semester->name }}
                            </flux:select.option>

                        @endforeach

                    </flux:select>

                </div>


                {{-- Beban Jam --}}
                <flux:input type="number" label="Beban Mengajar" wire:model="teachingHours" placeholder="Contoh: 4"
                    min="1" max="50" description="Masukkan jumlah Jam Pelajaran (JP) per minggu." />


                {{-- Status --}}
                <flux:checkbox wire:model="assignmentIsActive" label="Penugasan masih aktif" />

            </div>


            <div class="flex justify-end gap-2">

                <flux:button variant="ghost" wire:click="closeAssignmentForm">
                    Batal
                </flux:button>

                <flux:button variant="primary" wire:click="saveAssignment">
                    {{ $editingAssignmentId
    ? 'Simpan Perubahan'
    : 'Simpan'
                }}
                </flux:button>

            </div>

        </div>

    </flux:modal>

</div>
