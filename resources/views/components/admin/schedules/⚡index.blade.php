<?php

use App\Models\AcademicYear;
use App\Models\LessonPeriod;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    /*
    |--------------------------------------------------------------------------
    | Filter
    |--------------------------------------------------------------------------
    */

    public ?int $academicYearId = null;
    public ?int $semesterId = null;

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    public bool $showForm = false;
    public ?int $editingScheduleId = null;

    public ?int $teachingAssignmentId = null;
    public ?int $lessonPeriodId = null;
    public ?int $roomId = null;
    public ?int $dayOfWeek = null;
    public bool $isActive = true;
    public ?int $selectedSchoolClassId = null;
    /*
    |--------------------------------------------------------------------------
    | Mount
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $this->academicYearId = AcademicYear::query()
            ->where('is_active', true)
            ->value('id');

        $this->semesterId = Semester::query()
            ->where('is_active', true)
            ->value('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Data
    |--------------------------------------------------------------------------
    */

    public function getAcademicYearsProperty(): Collection
    {
        return AcademicYear::query()
            ->orderByDesc('start_date')
            ->get();
    }

    public function getSemestersProperty(): Collection
    {
        return Semester::query()
            ->orderBy('id')
            ->get();
    }

    public function getSchoolClassesProperty(): Collection
    {
        return SchoolClass::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getRoomsProperty(): Collection
    {
        return Room::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getLessonPeriodsProperty(): Collection
    {
        return LessonPeriod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function getTeachingAssignmentsProperty(): Collection
    {
        if (! $this->academicYearId || ! $this->semesterId) {
            return collect();
        }

        return TeachingAssignment::query()
        ->with([
            'gtk',
            'subject',
            'schoolClass',
        ])
        ->where('academic_year_id', $this->academicYearId)
        ->where('semester_id', $this->semesterId)
        ->when(
            $this->selectedSchoolClassId,
            fn (Builder $query) =>
                $query->where('school_class_id', $this->selectedSchoolClassId)
        )
        ->where('is_active', true)
        ->orderBy('school_class_id')
        ->orderBy('subject_id')
        ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Schedule Data
    |--------------------------------------------------------------------------
    */

    public function getSchedulesProperty(): Collection
    {
        if (! $this->academicYearId || ! $this->semesterId) {
            return collect();
        }

        return Schedule::query()
            ->with([
                'teachingAssignment.gtk',
                'teachingAssignment.subject',
                'teachingAssignment.schoolClass',
                'lessonPeriod',
                'room',
            ])
            ->where('is_active', true)
            ->whereHas('teachingAssignment', function (Builder $query) {
                $query
                    ->where('academic_year_id', $this->academicYearId)
                    ->where('semester_id', $this->semesterId);
            })
            ->get();
    }

    /**
     * Ambil jadwal berdasarkan hari + lesson period + kelas.
     */
    public function scheduleFor(
        int $dayOfWeek,
        int $lessonPeriodId,
        int $schoolClassId
    ): ?Schedule {
        return $this->schedules
            ->first(function (Schedule $schedule) use (
                $dayOfWeek,
                $lessonPeriodId,
                $schoolClassId
            ) {
                return
                    $schedule->day_of_week === $dayOfWeek
                    && $schedule->lesson_period_id === $lessonPeriodId
                    && $schedule->teachingAssignment->school_class_id === $schoolClassId;
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Days
    |--------------------------------------------------------------------------
    */

    public function days(): array
    {
        return [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Friday
    |--------------------------------------------------------------------------
    */

    public function periodsForDay(int $dayOfWeek): Collection
    {
        $periods = $this->lessonPeriods;

        // Senin-Kamis dan Sabtu menggunakan seluruh jadwal.
        if ($dayOfWeek !== 5) {
            return $periods;
        }

        /*
         * Jumat hanya sampai JP 5.
         *
         * Kita cari sort_order JP 5 agar slot waktu sebelumnya,
         * termasuk Istirahat 1, tetap ikut tampil.
         */
        $jp5 = $periods->firstWhere('period_number', 5);

        if (! $jp5) {
            return $periods;
        }

        return $periods
            ->where('sort_order', '<=', $jp5->sort_order)
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    public function createSchedule(
        ?int $dayOfWeek = null,
        ?int $lessonPeriodId = null,
        ?int $schoolClassId = null
    ): void {
        $this->resetForm();

        $this->dayOfWeek = $dayOfWeek;
        $this->lessonPeriodId = $lessonPeriodId;
        $this->selectedSchoolClassId = $schoolClassId;

        $this->showForm = true;
    }

    public function editSchedule(int $scheduleId): void
    {
        $schedule = Schedule::query()->findOrFail($scheduleId);

        $this->editingScheduleId = $schedule->id;
        $this->teachingAssignmentId = $schedule->teaching_assignment_id;
        $this->lessonPeriodId = $schedule->lesson_period_id;
        $this->roomId = $schedule->room_id;
        $this->dayOfWeek = $schedule->day_of_week;
        $this->isActive = $schedule->is_active;

        $this->resetValidation();

        $this->showForm = true;
    }

    public function saveSchedule(): void
    {
        $validated = $this->validate([
            'teachingAssignmentId' => ['required', 'integer', 'exists:teaching_assignments,id'],
            'lessonPeriodId' => ['required', 'integer', 'exists:lesson_periods,id'],
            'roomId' => ['required', 'integer', 'exists:rooms,id'],
            'dayOfWeek' => ['required', 'integer', 'in:1,2,3,4,5,6'],
            'isActive' => ['boolean'],
        ]);

        $assignment = TeachingAssignment::query()
            ->with([
                'gtk',
                'subject',
                'schoolClass',
            ])
            ->findOrFail($validated['teachingAssignmentId']);

        $lessonPeriod = LessonPeriod::query()
            ->findOrFail($validated['lessonPeriodId']);

        /*
         * Jangan izinkan Kegiatan Pagi / Istirahat menjadi jadwal KBM.
         */
        if ($lessonPeriod->is_break || $lessonPeriod->period_number === 0) {
            $this->addError(
                'lessonPeriodId',
                'Slot ini bukan jam pelajaran yang dapat digunakan untuk jadwal KBM.'
            );

            return;
        }

        /*
         * Pastikan assignment sesuai dengan tahun pelajaran
         * dan semester yang sedang dipilih.
         */
        if (
            $assignment->academic_year_id !== $this->academicYearId
            || $assignment->semester_id !== $this->semesterId
        ) {
            $this->addError(
                'teachingAssignmentId',
                'Mata pelajaran tersebut tidak sesuai dengan tahun pelajaran dan semester yang dipilih.'
            );

            return;
        }

        /*
         * Jumat hanya sampai JP 5.
         */
        if (
            $validated['dayOfWeek'] === 5
            && $lessonPeriod->period_number > 5
        ) {
            $this->addError(
                'lessonPeriodId',
                'Hari Jumat hanya sampai JP 5.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Cek jumlah JP guru
        |--------------------------------------------------------------------------
        */

        $currentSchedule = $this->editingScheduleId
            ? Schedule::query()->find($this->editingScheduleId)
            : null;

        $teachingHourCount = Schedule::query()
            ->where('is_active', true)
            ->where('teaching_assignment_id', $assignment->id)
            ->when(
                $this->editingScheduleId,
                fn (Builder $query) =>
                    $query->where('id', '!=', $this->editingScheduleId)
            )
            ->count();

        if (
            $teachingHourCount >= $assignment->teaching_hours
            && (
                ! $currentSchedule
                || ! $currentSchedule->is_active
                || ! $this->isActive
            )
        ) {
            // Tidak perlu blokir apabila hanya edit jadwal yang sama.
        } elseif (
            $teachingHourCount >= $assignment->teaching_hours
            && $this->isActive
        ) {
            $this->addError(
                'teachingAssignmentId',
                "Guru tersebut sudah memiliki {$assignment->teaching_hours} JP untuk mata pelajaran ini."
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Cek bentrok GURU
        |--------------------------------------------------------------------------
        */

        $teacherConflict = Schedule::query()
            ->where('day_of_week', $validated['dayOfWeek'])
            ->where('lesson_period_id', $validated['lessonPeriodId'])
            ->where('is_active', true)
            ->when(
                $this->editingScheduleId,
                fn (Builder $query) =>
                    $query->where('id', '!=', $this->editingScheduleId)
            )
            ->whereHas('teachingAssignment', function (Builder $query) use ($assignment) {
                $query
                    ->where('gtk_id', $assignment->gtk_id)
                    ->where('academic_year_id', $assignment->academic_year_id)
                    ->where('semester_id', $assignment->semester_id);
            })
            ->exists();

        if ($teacherConflict && $this->isActive) {
            $this->addError(
                'lessonPeriodId',
                'Guru tersebut sudah memiliki jadwal pada hari dan jam tersebut.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Cek bentrok KELAS
        |--------------------------------------------------------------------------
        */

        $classConflict = Schedule::query()
            ->where('day_of_week', $validated['dayOfWeek'])
            ->where('lesson_period_id', $validated['lessonPeriodId'])
            ->where('is_active', true)
            ->when(
                $this->editingScheduleId,
                fn (Builder $query) =>
                    $query->where('id', '!=', $this->editingScheduleId)
            )
            ->whereHas('teachingAssignment', function (Builder $query) use ($assignment) {
                $query
                    ->where('school_class_id', $assignment->school_class_id)
                    ->where('academic_year_id', $assignment->academic_year_id)
                    ->where('semester_id', $assignment->semester_id);
            })
            ->exists();

        if ($classConflict && $this->isActive) {
            $this->addError(
                'lessonPeriodId',
                'Kelas tersebut sudah memiliki jadwal pada hari dan jam tersebut.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Cek bentrok RUANG
        |--------------------------------------------------------------------------
        */

        $roomConflict = Schedule::query()
            ->where('day_of_week', $validated['dayOfWeek'])
            ->where('lesson_period_id', $validated['lessonPeriodId'])
            ->where('room_id', $validated['roomId'])
            ->where('is_active', true)
            ->when(
                $this->editingScheduleId,
                fn (Builder $query) =>
                    $query->where('id', '!=', $this->editingScheduleId)
            )
            ->exists();

        if ($roomConflict && $this->isActive) {
            $this->addError(
                'roomId',
                'Ruangan tersebut sudah digunakan pada hari dan jam tersebut.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Simpan
        |--------------------------------------------------------------------------
        */

        Schedule::query()->updateOrCreate(
            [
                'id' => $this->editingScheduleId,
            ],
            [
                'teaching_assignment_id' => $assignment->id,
                'lesson_period_id' => $validated['lessonPeriodId'],
                'room_id' => $validated['roomId'],
                'day_of_week' => $validated['dayOfWeek'],
                'is_active' => $this->isActive,
            ]
        );

        $this->showForm = false;
        $this->resetForm();

        session()->flash(
            'success',
            $this->editingScheduleId
                ? 'Jadwal berhasil diperbarui.'
                : 'Jadwal berhasil ditambahkan.'
        );
    }

    public function deleteSchedule(int $scheduleId): void
    {
        Schedule::query()
            ->findOrFail($scheduleId)
            ->delete();

        session()->flash('success', 'Jadwal berhasil dihapus.');
    }

    public function closeForm(): void
    {
        $this->showForm = false;

        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingScheduleId = null;
        $this->teachingAssignmentId = null;
        $this->lessonPeriodId = null;
        $this->roomId = null;
        $this->dayOfWeek = null;
        $this->selectedSchoolClassId = null;
        $this->isActive = true;

        $this->resetValidation();
    }

    /*
    |--------------------------------------------------------------------------
    | Render
    |--------------------------------------------------------------------------
    */

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
                Jadwal Madrasah
            </flux:heading>

            <flux:text class="mt-1">
                Menampilkan seluruh jadwal kegiatan pembelajaran berdasarkan kelas.
            </flux:text>
        </div>

        <flux:button
            variant="primary"
            icon="plus"
            wire:click="createSchedule"
        >
            Tambah Jadwal
        </flux:button>
    </div>

    {{-- Flash --}}
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
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

            <flux:select
                label="Tahun Pelajaran"
                wire:model.live="academicYearId"
            >
                <option value="">Pilih Tahun Pelajaran</option>

                @foreach ($this->academicYears as $academicYear)
                    <option value="{{ $academicYear->id }}">
                        {{ $academicYear->name }}
                    </option>
                @endforeach
            </flux:select>

            <flux:select
                label="Semester"
                wire:model.live="semesterId"
            >
                <option value="">Pilih Semester</option>

                @foreach ($this->semesters as $semester)
                    <option value="{{ $semester->id }}">
                        {{ $semester->name }}
                    </option>
                @endforeach
            </flux:select>

        </div>
    </flux:card>

    @if (! $academicYearId || ! $semesterId)

        <flux:card class="p-8 text-center">
            <flux:icon
                name="calendar-days"
                class="mx-auto size-12 text-zinc-400"
            />

            <flux:heading size="lg" class="mt-4">
                Pilih Tahun Pelajaran dan Semester
            </flux:heading>

            <flux:text class="mt-2">
                Silakan pilih filter untuk menampilkan jadwal madrasah.
            </flux:text>
        </flux:card>

    @else

        {{-- Info --}}
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">
                    Jadwal Seluruh Madrasah
                </flux:heading>

                <flux:text class="mt-1">
                    Setiap kolom menunjukkan satu kelas.
                </flux:text>
            </div>

            <flux:badge color="zinc">
                {{ $this->schoolClasses->count() }} Kelas
            </flux:badge>
        </div>

        @if ($this->schoolClasses->isEmpty())

            <flux:card class="p-8 text-center">
                <flux:icon
                    name="academic-cap"
                    class="mx-auto size-12 text-zinc-400"
                />

                <flux:heading size="lg" class="mt-4">
                    Belum ada kelas
                </flux:heading>

                <flux:text class="mt-2">
                    Belum ada data kelas aktif yang dapat ditampilkan.
                </flux:text>
            </flux:card>

        @else

            {{-- Jadwal per hari --}}
            <div class="space-y-8">

                @foreach ($this->days() as $dayNumber => $dayName)

                    <flux:card class="overflow-hidden">

                        {{-- Header Hari --}}
                        <div class="flex items-center justify-between border-b border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                            <div class="flex items-center gap-3">
                                <div class="flex size-9 items-center justify-center rounded-lg bg-zinc-900 text-sm font-semibold text-white dark:bg-white dark:text-zinc-900">
                                    {{ $dayNumber }}
                                </div>

                                <div>
                                    <flux:heading size="lg">
                                        {{ $dayName }}
                                    </flux:heading>

                                    <flux:text class="text-xs">
                                        Jadwal seluruh kelas
                                    </flux:text>
                                </div>
                            </div>
                        </div>

                        {{-- Tabel --}}
                        <div class="overflow-x-auto">

                            <table class="min-w-max w-full border-collapse text-sm">

                                <thead>
                                    <tr class="bg-zinc-100 dark:bg-zinc-800">

                                        {{-- Kolom Waktu --}}
                                        <th class="sticky left-0 z-20 w-32 min-w-32 border-b border-r border-zinc-200 bg-zinc-100 px-3 py-3 text-left font-semibold dark:border-zinc-700 dark:bg-zinc-800">
                                            Waktu
                                        </th>

                                        {{-- Semua Kelas --}}
                                        @foreach ($this->schoolClasses as $schoolClass)

                                            <th class="min-w-48 border-b border-r border-zinc-200 px-3 py-3 text-center font-semibold dark:border-zinc-700">
                                                <div>
                                                    {{ $schoolClass->name }}
                                                </div>

                                                @if ($schoolClass->code)
                                                    <div class="mt-0.5 text-xs font-normal text-zinc-500">
                                                        {{ $schoolClass->code }}
                                                    </div>
                                                @endif
                                            </th>

                                        @endforeach

                                    </tr>
                                </thead>

                                <tbody>

                                    @foreach ($this->periodsForDay($dayNumber) as $period)

                                        {{-- Break --}}
                                        @if ($period->is_break)

                                            <tr class="bg-amber-50 dark:bg-amber-950/20">

                                                <td class="sticky left-0 z-10 border-b border-r border-zinc-200 bg-amber-50 px-3 py-3 dark:border-zinc-700 dark:bg-amber-950/20">
                                                    <div class="font-semibold text-amber-700 dark:text-amber-400">
                                                        {{ $period->name }}
                                                    </div>

                                                    <div class="mt-0.5 text-xs text-zinc-500">
                                                        {{ \Carbon\Carbon::parse($period->start_time)->format('H:i') }}
                                                        –
                                                        {{ \Carbon\Carbon::parse($period->end_time)->format('H:i') }}
                                                    </div>
                                                </td>

                                                <td
                                                    colspan="{{ $this->schoolClasses->count() }}"
                                                    class="border-b border-zinc-200 px-3 py-3 text-center text-xs text-amber-700 dark:border-zinc-700 dark:text-amber-400"
                                                >
                                                    <div class="flex items-center justify-center gap-2">
                                                        <flux:icon
                                                            name="pause-circle"
                                                            class="size-4"
                                                        />

                                                        {{ $period->name }}
                                                    </div>
                                                </td>

                                            </tr>
                                        @elseif ($period->period_number === 0)

                                            {{-- Kegiatan Pagi --}}

                                            <tr class="bg-amber-50 dark:bg-amber-950/20">

                                                <td class="sticky left-0 z-10 border-b border-r border-zinc-200 bg-amber-50 px-3 py-3 dark:border-zinc-700 dark:bg-amber-950/20">
                                                    <div class="font-semibold text-amber-700 dark:text-amber-400">
                                                        {{ $period->name }}
                                                    </div>

                                                    <div class="mt-0.5 text-xs text-zinc-500">
                                                        {{ \Carbon\Carbon::parse($period->start_time)->format('H:i') }}
                                                        –
                                                        {{ \Carbon\Carbon::parse($period->end_time)->format('H:i') }}
                                                    </div>
                                                </td>

                                                <td
                                                    colspan="{{ $this->schoolClasses->count() }}"
                                                    class="border-b border-zinc-200 px-3 py-3 text-center text-xs text-amber-700 dark:border-zinc-700 dark:text-amber-400"
                                                >
                                                    <div class="flex items-center justify-center gap-2">
                                                        <flux:icon
                                                            name="pause-circle"
                                                            class="size-4"
                                                        />

                                                        {{ $period->name }}
                                                    </div>
                                                </td>

                                            </tr>

                                        @else

                                            {{-- Jam Pelajaran --}}
                                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30">

                                                {{-- Waktu --}}
                                                <td class="sticky left-0 z-10 border-b border-r border-zinc-200 bg-white px-3 py-3 dark:border-zinc-700 dark:bg-zinc-900">

                                                    <div class="font-semibold">
                                                        @if ($period->period_number === 0)
                                                            Kegiatan Pagi
                                                        @else
                                                            JP {{ $period->period_number }}
                                                        @endif
                                                    </div>

                                                    <div class="mt-1 text-xs text-zinc-500">
                                                        {{ \Carbon\Carbon::parse($period->start_time)->format('H:i') }}
                                                        –
                                                        {{ \Carbon\Carbon::parse($period->end_time)->format('H:i') }}
                                                    </div>

                                                </td>

                                                {{-- Per Kelas --}}
                                                @foreach ($this->schoolClasses as $schoolClass)

                                                    @php
                                                        $schedule = $this->scheduleFor(
                                                            $dayNumber,
                                                            $period->id,
                                                            $schoolClass->id
                                                        );
                                                    @endphp

                                                    <td class="border-b border-r border-zinc-200 p-2 align-top dark:border-zinc-700">

                                                        @if ($schedule)

                                                            <div class="group relative rounded-lg border border-zinc-200 bg-white p-3 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800">

                                                                {{-- Mata Pelajaran --}}
                                                                <div class="font-semibold leading-tight">
                                                                    {{ $schedule->teachingAssignment->subject->name }}
                                                                </div>

                                                                {{-- Guru --}}
                                                                <div class="mt-2 flex items-start gap-1.5 text-xs text-zinc-600 dark:text-zinc-300">

                                                                    <flux:icon
                                                                        name="user"
                                                                        class="mt-0.5 size-3.5 shrink-0"
                                                                    />

                                                                    <span>
                                                                        {{ $schedule->teachingAssignment->gtk->full_name }}
                                                                    </span>

                                                                </div>

                                                                {{-- Ruang --}}
                                                                <div class="mt-1 flex items-center gap-1.5 text-xs text-zinc-500">

                                                                    <flux:icon
                                                                        name="map-pin"
                                                                        class="size-3.5 shrink-0"
                                                                    />

                                                                    <span>
                                                                        {{ $schedule->room->name }}
                                                                    </span>

                                                                </div>

                                                                {{-- Actions --}}
<div class="mt-3 flex items-center gap-1 border-t border-zinc-100 pt-2 dark:border-zinc-700">

    <flux:button
        size="xs"
        variant="ghost"
        icon="pencil"
        wire:click="editSchedule({{ $schedule->id }})"
        title="Edit"
    />

    <flux:button
        size="xs"
        variant="ghost"
        icon="trash"
        class="text-red-600 hover:text-red-700"
        wire:click="deleteSchedule({{ $schedule->id }})"
        wire:confirm="Yakin ingin menghapus jadwal ini?"
        title="Hapus"
    />

</div>

                                                            </div>

                                                        @else

                                                            {{-- Slot kosong --}}
                                                            <button
                                                                type="button"
                                                                wire:click="createSchedule({{ $dayNumber }}, {{ $period->id }}, {{ $schoolClass->id }})"
                                                                class="flex min-h-24 w-full items-center justify-center rounded-lg border border-dashed border-zinc-200 text-zinc-400 transition hover:border-zinc-400 hover:bg-zinc-50 hover:text-zinc-600 dark:border-zinc-700 dark:hover:bg-zinc-800"
                                                            >
                                                                <div class="text-center">
                                                                    <flux:icon
                                                                        name="plus"
                                                                        class="mx-auto size-4"
                                                                    />

                                                                    <span class="mt-1 block text-xs">
                                                                        Tambah
                                                                    </span>
                                                                </div>
                                                            </button>

                                                        @endif

                                                    </td>

                                                @endforeach

                                            </tr>

                                        @endif

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    </flux:card>

                @endforeach

            </div>

        @endif

    @endif


    {{-- Modal Tambah / Edit --}}
    <flux:modal
        wire:model="showForm"
        class="md:w-[600px]"
    >

        <div class="space-y-6">

            <div>
                <flux:heading size="lg">
                    {{ $editingScheduleId ? 'Edit Jadwal' : 'Tambah Jadwal' }}
                </flux:heading>

                <flux:text class="mt-1">
                    Tentukan guru, mata pelajaran, kelas, hari, jam, dan ruang.
                </flux:text>
            </div>

            <div class="space-y-4">

                {{-- Teaching Assignment --}}
                <flux:select
                    label="Mata Pelajaran / Guru / Kelas"
                    wire:model="teachingAssignmentId"
                    searchable
                >

                    <option value="">
                        Pilih mata pelajaran
                    </option>

                    @php
                        $groupedAssignments = $this->teachingAssignments->groupBy(
                            fn ($assignment) => $assignment->schoolClass->name
                        );
                    @endphp

                    @foreach ($groupedAssignments as $className => $assignments)

                        <optgroup label="{{ $className }}">

                            @foreach ($assignments as $assignment)

                                <option value="{{ $assignment->id }}">
                                    {{ $assignment->subject->name }}
                                    — {{ $assignment->gtk->full_name }}
                                    ({{ $assignment->teaching_hours }} JP)
                                </option>

                            @endforeach

                        </optgroup>

                    @endforeach

                </flux:select>

                @error('teachingAssignmentId')
                    <flux:text class="text-sm text-red-600">
                        {{ $message }}
                    </flux:text>
                @enderror


                {{-- Hari --}}
                <flux:select
                    label="Hari"
                    wire:model="dayOfWeek"
                >

                    <option value="">
                        Pilih hari
                    </option>

                    @foreach ($this->days() as $dayNumber => $dayName)
                        <option value="{{ $dayNumber }}">
                            {{ $dayName }}
                        </option>
                    @endforeach

                </flux:select>


                {{-- Jam --}}
                <flux:select
                    label="Jam Pelajaran"
                    wire:model="lessonPeriodId"
                >

                    <option value="">
                        Pilih jam
                    </option>

                    @foreach ($this->lessonPeriods as $period)

                        @if (! $period->is_break && $period->period_number !== 0)

                            <option value="{{ $period->id }}">
                                JP {{ $period->period_number }}
                                —
                                {{ \Carbon\Carbon::parse($period->start_time)->format('H:i') }}
                                -
                                {{ \Carbon\Carbon::parse($period->end_time)->format('H:i') }}
                            </option>

                        @endif

                    @endforeach

                </flux:select>

                @error('lessonPeriodId')
                    <flux:text class="text-sm text-red-600">
                        {{ $message }}
                    </flux:text>
                @enderror


                {{-- Ruang --}}
                <flux:select
                    label="Ruang"
                    wire:model="roomId"
                >

                    <option value="">
                        Pilih ruang
                    </option>

                    @foreach ($this->rooms as $room)

                        <option value="{{ $room->id }}">
                            {{ $room->name }}
                            @if ($room->building)
                                — {{ $room->building }}
                            @endif
                        </option>

                    @endforeach

                </flux:select>

                @error('roomId')
                    <flux:text class="text-sm text-red-600">
                        {{ $message }}
                    </flux:text>
                @enderror


                {{-- Status --}}
                <flux:checkbox
                    wire:model="isActive"
                    label="Jadwal aktif"
                />

            </div>

            {{-- Footer --}}
            <div class="flex justify-end gap-2">

                <flux:button
                    variant="ghost"
                    wire:click="closeForm"
                >
                    Batal
                </flux:button>

                <flux:button
                    variant="primary"
                    wire:click="saveSchedule"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="saveSchedule">
                        {{ $editingScheduleId ? 'Simpan Perubahan' : 'Simpan Jadwal' }}
                    </span>

                    <span wire:loading wire:target="saveSchedule">
                        Menyimpan...
                    </span>
                </flux:button>

            </div>

        </div>

    </flux:modal>

</div>
