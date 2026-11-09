<?php

use App\Models\AcademicYear;
use App\Models\LessonPeriod;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\TeachingAssignment;
use App\Services\ScheduleSessionService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
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

    #[Locked]
    public ?int $schoolClassId = null;
    public ?int $academicYearId = null;

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    public bool $showForm = false;
    #[Locked]
    public ?int $editingScheduleId = null;

    public ?int $teachingAssignmentId = null;
    public ?int $lessonPeriodId = null;
    public ?int $duration = 1;
    public ?int $roomId = null;
    public ?int $dayOfWeek = null;
    public bool $isActive = true;
    public ?int $selectedSchoolClassId = null;
    /*
    |--------------------------------------------------------------------------
    | Mount
    |--------------------------------------------------------------------------
    */

    public function mount(?int $schoolClassId = null, ?int $initialAcademicYearId = null): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $schoolClassId ??= request()->integer('school_class_id') ?: null;
        $initialAcademicYearId ??= request()->integer('academic_year_id') ?: null;
        $this->schoolClassId = $schoolClassId;
        if ($schoolClassId) SchoolClass::findOrFail($schoolClassId);
        $this->academicYearId = AcademicYear::query()
            ->where('is_active', true)
            ->value('id');

        if ($initialAcademicYearId) {
            $this->academicYearId = AcademicYear::findOrFail($initialAcademicYearId)->id;
        }
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

    public function updatedAcademicYearId(): void
    {
        $this->schoolClassId = null;
        $this->closeForm();
    }

    public function getSchoolClassesProperty(): Collection
    {
        return SchoolClass::query()->where('academic_year_id', $this->academicYearId)
            ->when($this->schoolClassId, fn ($q) => $q->whereKey($this->schoolClassId), fn ($q) => $q->where('is_active', true))
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
            ->orderBy('start_time')->orderBy('period_number')
            ->get();
    }

    public function getTeachingAssignmentsProperty(): Collection
    {
        if (! $this->academicYearId) {
            return collect();
        }

        return TeachingAssignment::query()
        ->withCount('activeSchedules as scheduled_hours')
        ->with([
            'gtk',
            'subject',
            'schoolClass',
        ])
        ->where('academic_year_id', $this->academicYearId)
        ->when(
            $this->selectedSchoolClassId,
            fn (Builder $query) =>
                $query->where('school_class_id', $this->selectedSchoolClassId)
        )
        ->when($this->schoolClassId, fn ($q) => $q->where('school_class_id', $this->schoolClassId))
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
        if (! $this->academicYearId) {
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
            ->whereHas('lessonPeriod', fn ($query) => $query->where('is_active', true))
            ->whereHas('teachingAssignment', function (Builder $query) {
                $query
                    ->when($this->schoolClassId, fn ($q) => $q->where('school_class_id', $this->schoolClassId))
                    ->where('academic_year_id', $this->academicYearId)
                    ->where('is_active', true);
            })
            ->get()->filter(fn ($schedule) => $this->periodsForDay($schedule->day_of_week)->contains('id', $schedule->lesson_period_id));
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
        return app(ScheduleSessionService::class)->periodsForDay($dayOfWeek, $this->lessonPeriods);
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
        $this->selectedSchoolClassId = $this->schoolClassId ?? $schoolClassId;
        $this->useClassroom($this->selectedSchoolClassId);

        $this->showForm = true;
    }

    public function updatedTeachingAssignmentId(): void
    {
        $assignment = $this->teachingAssignments->firstWhere('id', $this->teachingAssignmentId);
        $this->useClassroom($assignment?->school_class_id);
    }

    public function updatedDayOfWeek(): void
    {
        $current = $this->lessonPeriodId ? LessonPeriod::find($this->lessonPeriodId) : null;
        $replacement = $current && $this->dayOfWeek ? $this->periodsForDay($this->dayOfWeek)
            ->first(fn ($period) => ! $period->is_break && $period->period_number > 0 && $period->period_number === $current->period_number) : null;
        $this->lessonPeriodId = $replacement?->id;
    }

    private function useClassroom(?int $classId): void
    {
        $room = $classId ? SchoolClass::find($classId)?->room : null;
        $this->roomId = $room?->is_active ? $room->id : null;
    }

    public function editSchedule(int $scheduleId): void
    {
        $this->resetForm();
        $this->assertClassSchedule($scheduleId);
        $session = app(ScheduleSessionService::class)->sessionFor($scheduleId);
        $schedule = $session->schedules->first();
        $this->editingScheduleId = $schedule->id;
        $this->teachingAssignmentId = $schedule->teaching_assignment_id;
        $this->selectedSchoolClassId = $schedule->teachingAssignment->school_class_id;
        $this->lessonPeriodId = $schedule->lesson_period_id;
        $this->duration = $session->schedules->count();
        $this->roomId = $schedule->room_id;
        $this->dayOfWeek = $schedule->day_of_week;
        $this->isActive = $schedule->is_active;
        $this->showForm = true;
    }

    public function getSessionPreviewProperty(): array
    {
        if (! $this->lessonPeriodId || ! $this->dayOfWeek) {
            return ['periods' => collect(), 'error' => null];
        }
        if ($this->duration === null) {
            return ['periods' => collect(), 'error' => 'Isi durasi sesi dalam JP.'];
        }
        try {
            return ['periods' => app(ScheduleSessionService::class)->periodSpan($this->lessonPeriodId, $this->duration, $this->dayOfWeek), 'error' => null];
        } catch (ValidationException $exception) {
            return ['periods' => collect(), 'error' => collect($exception->errors())->flatten()->first()];
        }
    }

    public function getScheduleSessionMapProperty(): Collection
    {
        $map = collect();
        foreach (app(ScheduleSessionService::class)->group($this->schedules) as $session) {
            foreach ($session->schedules as $schedule) {
                $map->put($schedule->id, $session);
            }
        }
        return $map;
    }

    public function saveSchedule(): void
    {
        if ($this->schoolClassId) {
            abort_unless(TeachingAssignment::whereKey($this->teachingAssignmentId)->where('school_class_id', $this->schoolClassId)->exists(), 422);
        }
        $editing = $this->editingScheduleId !== null;
        app(ScheduleSessionService::class)->save(auth()->user(), $this->only([
            'academicYearId', 'teachingAssignmentId', 'lessonPeriodId', 'duration', 'roomId', 'dayOfWeek', 'isActive',
        ]), $this->editingScheduleId);
        $this->showForm = false;
        $this->resetForm();
        session()->flash('success', $editing ? 'Sesi jadwal berhasil diperbarui.' : 'Sesi jadwal berhasil ditambahkan.');
    }

    public function deleteSchedule(int $scheduleId): void
    {
        $this->assertClassSchedule($scheduleId);
        app(ScheduleSessionService::class)->delete(auth()->user(), $scheduleId);
        session()->flash('success', 'Sesi jadwal berhasil dihapus.');
    }

    private function assertClassSchedule(int $id): void
    {
        if ($this->schoolClassId) abort_unless(Schedule::whereKey($id)->whereHas('teachingAssignment', fn ($q) => $q->where('school_class_id', $this->schoolClassId))->exists(), 404);
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
        $this->duration = 1;
        $this->roomId = null;
        $this->dayOfWeek = null;
        $this->selectedSchoolClassId = $this->schoolClassId;
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
    <div class="space-y-4">
        <div>
            <flux:heading size="xl">
                {{ $schoolClassId ? 'Jadwal Pelajaran Kelas' : 'Jadwal Madrasah' }}
            </flux:heading>

            <flux:text class="mt-1">
                Kelola jadwal per sesi. 1 JP = 35 menit; jam kegiatan pagi dan istirahat mengikuti pola setiap hari.
            </flux:text>
        </div>

        <div class="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:items-center" role="group" aria-label="Pengelolaan jadwal">
            <flux:button
                variant="primary"
                icon="plus"
                wire:click="createSchedule"
            >
                Tambah Jadwal
            </flux:button>
            <flux:button
                icon="user-group"
                :href="route('admin.schedules.assignments', ['academic_year_id' => $academicYearId, 'class_id' => $schoolClassId])"
                wire:navigate
            >
                Kelola Pengampu
            </flux:button>
            <flux:button :href="route('admin.kbm-times.index')" icon="clock" wire:navigate>
                Pengaturan Waktu KBM
            </flux:button>
        </div>
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
    @error('schedule')
        <flux:callout variant="warning">{{ $message }}</flux:callout>
    @enderror
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

        </div>
    </flux:card>

    @if (! $academicYearId)

        <flux:card class="p-8 text-center">
            <flux:icon
                name="calendar-days"
                class="mx-auto size-12 text-zinc-400"
            />

            <flux:heading size="lg" class="mt-4">
                Pilih Tahun Pelajaran
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
                    {{ $schoolClassId ? 'Jadwal Kelas' : 'Jadwal Seluruh Madrasah' }}
                </flux:heading>

                <flux:text class="mt-1">
                    Setiap kolom menunjukkan satu kelas. Sesi beberapa JP ditampilkan dalam satu blok.
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
                                                            {{ $period->name }}
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

                                                    @php
                                                        $scheduleSession = $schedule ? $this->scheduleSessionMap->get($schedule->id) : null;
                                                        $scheduleSegment = $scheduleSession?->segments->first(fn ($segment) => $segment->schedules->contains('id', $schedule->id));
                                                    @endphp
                                                    @if ($scheduleSegment && $scheduleSegment->id !== $schedule->id)
                                                        @continue
                                                    @endif
                                                    <td rowspan="{{ $scheduleSegment?->schedules->count() ?? 1 }}" class="border-b border-r border-zinc-200 p-2 align-top dark:border-zinc-700">

                                                        @if ($schedule)

                                                            <div class="group relative rounded-lg border border-zinc-200 bg-white p-3 shadow-sm transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800">

                                                                {{-- Mata Pelajaran --}}
                                                                <div class="font-semibold leading-tight">
                                                                    {{ $schedule->teachingAssignment->subject->name }}
                                                                </div>
                                                                <flux:badge class="mt-2" size="sm" color="blue">{{ $scheduleSession->schedules->count() }} JP</flux:badge>
                                                                @if ($scheduleSession->id !== $schedule->id)
                                                                    <div class="mt-1 text-xs font-semibold text-blue-600">Lanjutan setelah istirahat</div>
                                                                @endif
                                                                <div class="mt-1 text-xs text-zinc-500">{{ $scheduleSession->lesson_periods->first()->name }}@if($scheduleSession->schedules->count() > 1) – {{ $scheduleSession->lesson_periods->last()->name }}@endif</div>

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
        wire:confirm="Hapus seluruh jam pelajaran dalam sesi ini?"
        title="Hapus"
    />

</div>

                                                            </div>

                                                        @elseif ($period->period_number > 0)

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
                    Pilih jam mulai dan durasi sesi, misalnya 2 JP untuk satu pelajaran dua jam.
                </flux:text>
            </div>

            <div class="space-y-4">

                {{-- Teaching Assignment --}}
                <flux:select
                    label="Mata Pelajaran / Guru / Kelas"
                    wire:model.live="teachingAssignmentId"
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
                                    ({{ $assignment->scheduled_hours }} JP terjadwal)
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
                    wire:model.live="dayOfWeek"
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
                    label="Jam mulai"
                    wire:model.live="lessonPeriodId"
                >

                    <option value="">
                        {{ $dayOfWeek ? 'Pilih jam' : 'Pilih hari terlebih dahulu' }}
                    </option>

                    @foreach (($this->dayOfWeek ? $this->periodsForDay($this->dayOfWeek) : collect()) as $period)

                        @if (! $period->is_break && $period->period_number !== 0)

                            <option value="{{ $period->id }}">
                                {{ $period->name }}
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

                <flux:input type="number" label="Durasi sesi (JP)" min="1" max="12" step="1" wire:model.live="duration" />
                @error('duration')
                    <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                @enderror
                @php
                    $preview = $this->sessionPreview;
                @endphp
                @if ($preview['error'])
                    <flux:callout variant="warning">{{ $preview['error'] }}</flux:callout>
                @elseif ($preview['periods']->isNotEmpty())
                    <flux:callout icon="clock">
                        <div class="font-semibold">{{ $duration }} JP · {{ $preview['periods']->first()->name }}@if($duration > 1) – {{ $preview['periods']->last()->name }}@endif</div>
                        <div>{{ substr($preview['periods']->first()->start_time, 0, 5) }} – {{ substr($preview['periods']->last()->end_time, 0, 5) }} · Presensi siswa cukup sekali untuk sesi ini.</div>
                        @foreach ($this->periodsForDay($dayOfWeek)->where('is_break', true) as $break)
                            @if ($break->start_time >= $preview['periods']->first()->end_time && $break->end_time <= $preview['periods']->last()->start_time)
                                <div>Jeda {{ strtolower($break->name) }}: {{ substr($break->start_time, 0, 5) }} – {{ substr($break->end_time, 0, 5) }}. Pelajaran dilanjutkan setelahnya.</div>
                            @endif
                        @endforeach
                    </flux:callout>
                @endif
                <flux:text size="sm">1 JP = 35 menit. Sesi boleh berlanjut setelah istirahat; waktu istirahat tidak dihitung sebagai JP.</flux:text>
                @error('schedule')
                    <flux:callout variant="warning">{{ $message }}</flux:callout>
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
                <flux:text size="sm">Ruang mengikuti pengaturan kelas dan dapat diganti untuk sesi ini. Jika belum terisi, tentukan ruang kelas melalui menu Kelas atau pilih ruangan di sini.</flux:text>


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
