<?php

use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentAttendance;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public string $attendanceDate = '';

    // ID schedule pertama menjadi ID/anchor untuk satu sesi.
    public ?int $scheduleId = null;

    public array $attendance = [];

    public array $notes = [];

    public function mount(): void
    {
        $this->attendanceDate = now()->format('Y-m-d');
    }

    public function updatedScheduleId(): void
    {
        $this->loadStudents();
    }

    public function updatedAttendanceDate(): void
    {
        $this->scheduleId = null;
        $this->attendance = [];
        $this->notes = [];
    }

    /**
     * Semua jadwal pada tanggal tersebut dikelompokkan
     * menjadi sesi KBM.
     *
     * Contoh:
     * JP 3 → Bahasa Arab
     * JP 4 → Bahasa Arab
     *
     * menjadi satu sesi:
     * Bahasa Arab — JP 3-4
     */
    public function getAttendanceSessionsProperty(): Collection
    {
        if (! $this->attendanceDate) {
            return collect();
        }

        $dayOfWeek = Carbon::parse($this->attendanceDate)->dayOfWeekIso;

        $schedules = Schedule::query()
            ->with([
                'teachingAssignment.schoolClass',
                'teachingAssignment.subject',
                'teachingAssignment.gtk',
                'lessonPeriod',
                'room',
            ])
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn ($schedule) => $schedule->lessonPeriod->sort_order)
            ->values();

        $sessions = collect();

        foreach ($schedules as $schedule) {
            $lastSession = $sessions->last();

            $canMerge = $lastSession
                && $lastSession->teaching_assignment_id === $schedule->teaching_assignment_id
                && $lastSession->lesson_periods->last()->sort_order + 1 ===
                    $schedule->lessonPeriod->sort_order;

            if ($canMerge) {
                $lastSession->schedules->push($schedule);
                $lastSession->lesson_periods->push($schedule->lessonPeriod);

                $sessions->pop();
                $sessions->push($lastSession);

                continue;
            }

            $sessions->push((object) [
                // Schedule pertama menjadi ID sesi
                'id' => $schedule->id,

                'teaching_assignment_id' => $schedule->teaching_assignment_id,

                'teachingAssignment' => $schedule->teachingAssignment,

                'room' => $schedule->room,

                'schedules' => collect([$schedule]),

                'lesson_periods' => collect([$schedule->lessonPeriod]),
            ]);
        }

        return $sessions->values();
    }

    /**
     * Sesi yang sedang dipilih.
     */
    public function getSelectedSessionProperty(): ?object
    {
        if (! $this->scheduleId) {
            return null;
        }

        return $this->attendanceSessions
            ->firstWhere('id', $this->scheduleId);
    }

    /**
     * Load daftar siswa sesuai kelas pada sesi yang dipilih.
     */
    public function loadStudents(): void
    {
        $this->attendance = [];
        $this->notes = [];

        if (! $this->scheduleId || ! $this->attendanceDate) {
            return;
        }

        $session = $this->attendanceSessions
            ->firstWhere('id', $this->scheduleId);

        if (! $session) {
            return;
        }

        // Schedule pertama menjadi identitas sesi presensi.
        $schedule = $session->schedules->first();

        $teachingAssignment = $schedule->teachingAssignment;

        $students = Student::query()
            ->where('is_active', true)
            ->whereHas('classHistories', function ($query) use ($teachingAssignment) {
                $query
                    ->where(
                        'school_class_id',
                        $teachingAssignment->school_class_id
                    )
                    ->where(
                        'academic_year_id',
                        $teachingAssignment->academic_year_id
                    )
                    ->where(
                        'semester_id',
                        $teachingAssignment->semester_id
                    )
                    ->where('is_active', true);
            })
            ->orderBy('full_name')
            ->get();

        /*
         * Presensi hanya dicari berdasarkan schedule pertama.
         * Jadi JP kedua tidak membuat record presensi baru.
         */
        $existing = StudentAttendance::query()
            ->where('schedule_id', $schedule->id)
            ->whereDate('attendance_date', $this->attendanceDate)
            ->get()
            ->keyBy('student_id');

        foreach ($students as $student) {
            $record = $existing->get($student->id);

            $this->attendance[$student->id] =
                $record?->status ?? 'present';

            $this->notes[$student->id] =
                $record?->notes ?? '';
        }
    }

    /**
     * Simpan presensi siswa.
     */
    public function saveAttendance(): void
    {
        $this->validate([
            'attendanceDate' => ['required', 'date'],
            'scheduleId' => ['required', 'exists:schedules,id'],
            'attendance' => ['required', 'array'],
            'attendance.*' => [
                'required',
                'in:present,late,excused,sick,absent',
            ],
            'notes.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $session = $this->attendanceSessions
            ->firstWhere('id', $this->scheduleId);

        if (! $session) {
            return;
        }

        // Schedule pertama menjadi anchor/identitas sesi.
        $schedule = $session->schedules->first();

        foreach ($this->attendance as $studentId => $status) {
            StudentAttendance::updateOrCreate(
                [
                    'schedule_id' => $schedule->id,
                    'student_id' => $studentId,
                    'attendance_date' => $this->attendanceDate,
                ],
                [
                    'status' => $status,
                    'notes' => $this->notes[$studentId] ?? null,
                    'recorded_by' => Auth::id(),
                    'scanned_at' => now(),
                ]
            );
        }

        session()->flash(
            'success',
            'Presensi siswa berhasil disimpan.'
        );
    }

    /**
     * Daftar siswa yang sedang ditampilkan.
     */
    public function getStudentsProperty(): Collection
    {
        if (! $this->scheduleId || empty($this->attendance)) {
            return collect();
        }

        return Student::query()
            ->whereIn('id', array_keys($this->attendance))
            ->orderBy('full_name')
            ->get();
    }
};
?>

<div class="space-y-6">

    {{-- Header --}}
    <div>
        <flux:heading size="xl">
            Presensi Siswa
        </flux:heading>

        <flux:text class="mt-1">
            Input presensi siswa berdasarkan sesi KBM.
        </flux:text>
    </div>

    {{-- Success --}}
    @if (session('success'))
        <flux:callout variant="success">
            {{ session('success') }}
        </flux:callout>
    @endif

    {{-- Filter --}}
    <flux:card>
        <div class="grid gap-4 md:grid-cols-2">

            <flux:input
                type="date"
                wire:model.live="attendanceDate"
                label="Tanggal"
            />

            <flux:select
                wire:model.live="scheduleId"
                label="Sesi KBM"
            >
                <option value="">Pilih sesi KBM</option>

                @foreach ($this->attendanceSessions as $session)
                    @php
                        $firstPeriod = $session->lesson_periods->first();
                        $lastPeriod = $session->lesson_periods->last();
                    @endphp

                    <option value="{{ $session->id }}">
                        {{ $session->teachingAssignment->schoolClass->name }}
                        —
                        {{ $session->teachingAssignment->subject->name }}
                        —
                        {{ $firstPeriod->name }}

                        @if ($firstPeriod->id !== $lastPeriod->id)
                            - {{ $lastPeriod->name }}
                        @endif
                    </option>
                @endforeach
            </flux:select>

        </div>
    </flux:card>

    {{-- Informasi sesi --}}
    @if ($this->selectedSession)
        @php
            $session = $this->selectedSession;
            $firstPeriod = $session->lesson_periods->first();
            $lastPeriod = $session->lesson_periods->last();
        @endphp

        <flux:card>

            <div class="grid gap-4 md:grid-cols-5">

                {{-- Kelas --}}
                <div>
                    <flux:text size="sm">
                        Kelas
                    </flux:text>

                    <flux:heading size="lg">
                        {{ $session->teachingAssignment->schoolClass->name }}
                    </flux:heading>
                </div>

                {{-- Mapel --}}
                <div>
                    <flux:text size="sm">
                        Mata Pelajaran
                    </flux:text>

                    <flux:heading size="lg">
                        {{ $session->teachingAssignment->subject->name }}
                    </flux:heading>
                </div>

                {{-- Guru --}}
                <div>
                    <flux:text size="sm">
                        Guru
                    </flux:text>

                    <flux:heading size="lg">
                        {{ $session->teachingAssignment->gtk->full_name }}
                    </flux:heading>
                </div>

                {{-- Jam --}}
                <div>
                    <flux:text size="sm">
                        Jam
                    </flux:text>

                    <flux:heading size="lg">
                        {{ $firstPeriod->name }}

                        @if ($firstPeriod->id !== $lastPeriod->id)
                            - {{ $lastPeriod->name }}
                        @endif
                    </flux:heading>
                </div>

                {{-- Ruangan --}}
                <div>
                    <flux:text size="sm">
                        Ruangan
                    </flux:text>

                    <flux:heading size="lg">
                        {{ $session->room->name ?? '-' }}
                    </flux:heading>
                </div>

            </div>

        </flux:card>
    @endif

    {{-- Daftar siswa --}}
    @if ($this->selectedSession && $this->students->isNotEmpty())

        <flux:card>

            <div class="mb-4 flex items-center justify-between">

                <div>
                    <flux:heading size="lg">
                        Daftar Siswa
                    </flux:heading>

                    <flux:text>
                        {{ $this->students->count() }} siswa
                    </flux:text>
                </div>

                <flux:button
                    variant="primary"
                    wire:click="saveAttendance"
                    wire:loading.attr="disabled"
                >
                    Simpan Presensi
                </flux:button>

            </div>

            <div class="overflow-x-auto">

                <table class="w-full text-sm">

                    <thead>
                        <tr class="border-b text-left">
                            <th class="w-12 px-3 py-3">
                                No
                            </th>

                            <th class="px-3 py-3">
                                Nama Siswa
                            </th>

                            <th class="px-3 py-3">
                                NIS
                            </th>

                            <th class="px-3 py-3">
                                Status
                            </th>

                            <th class="px-3 py-3">
                                Catatan
                            </th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach ($this->students as $index => $student)

                            <tr
                                wire:key="student-{{ $student->id }}"
                                class="border-b"
                            >

                                <td class="px-3 py-3">
                                    {{ $index + 1 }}
                                </td>

                                <td class="px-3 py-3 font-medium">
                                    {{ $student->full_name }}
                                </td>

                                <td class="px-3 py-3">
                                    {{ $student->nis ?? '-' }}
                                </td>

                                <td class="px-3 py-3">

                                    <flux:select
                                        wire:model="attendance.{{ $student->id }}"
                                    >
                                        <option value="present">
                                            Hadir
                                        </option>

                                        <option value="late">
                                            Terlambat
                                        </option>

                                        <option value="excused">
                                            Izin
                                        </option>

                                        <option value="sick">
                                            Sakit
                                        </option>

                                        <option value="absent">
                                            Alpa
                                        </option>
                                    </flux:select>

                                </td>

                                <td class="px-3 py-3">

                                    <flux:input
                                        wire:model="notes.{{ $student->id }}"
                                        placeholder="Catatan..."
                                    />

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        </flux:card>

    @elseif ($this->selectedSession)

        <flux:card>

            <flux:callout variant="warning">
                Tidak ada siswa aktif pada kelas ini.
            </flux:callout>

        </flux:card>

    @endif

</div>
