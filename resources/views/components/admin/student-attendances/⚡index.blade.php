<?php

use App\Models\Student;
use App\Models\StudentAttendance;
use App\Services\StudentAttendanceSessionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    public string $attendanceDate = '';

    // ID schedule pertama menjadi ID/anchor untuk satu sesi.
    public ?int $scheduleId = null;

    public array $attendance = [];

    public array $notes = [];

    public bool $scanMode = false;

    public ?int $lastScannedStudentId = null;

    public string $scanMessage = '';

    public string $scanMessageType = 'info';

    #[Locked]
    public array $scannedAt = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->hasActiveRole(['admin', 'staff', 'teacher', 'headmaster']), 403);
        $this->attendanceDate = now()->format('Y-m-d');
        $query = validator(request()->only(['date', 'schedule']), [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'schedule' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        $this->attendanceDate = $query['date'] ?? $this->attendanceDate;
        if (!empty($query['schedule'])) {
            $this->scheduleId = (int) $query['schedule'];
            $this->loadStudents();
        }
    }

    public function updatedScheduleId(): void
    {
        $this->stopScanner();
        $this->lastScannedStudentId = null;
        $this->loadStudents();
    }

    public function updatedAttendanceDate(): void
    {
        $this->stopScanner();
        $this->lastScannedStudentId = null;
        $this->scheduleId = null;
        $this->attendance = [];
        $this->notes = [];
        $this->scannedAt = [];
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
        if (validator(['date' => $this->attendanceDate], ['date' => 'required|date_format:Y-m-d'])->fails()) {
            return collect();
        }

        return app(StudentAttendanceSessionService::class)->forUser(Auth::user(), $this->attendanceDate);
    }

    /**
     * Sesi yang sedang dipilih.
     */
    public function getSelectedSessionProperty(): ?object
    {
        if (!$this->scheduleId) {
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
        $this->scannedAt = [];
        $this->resetValidation();

        if (!$this->scheduleId || !$this->attendanceDate) {
            return;
        }

        $session = $this->attendanceSessions
            ->firstWhere('id', $this->scheduleId);

        abort_unless($session, 403);

        // Schedule pertama menjadi identitas sesi presensi.
        $schedule = $session->schedules->first();

        $students = $this->studentsForSession($session);

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
                $record?->status ?? '';

            $this->notes[$student->id] =
                $record?->notes ?? '';
            if ($record?->scanned_at) {
                $this->scannedAt[$student->id] = $record->scanned_at->toIso8601String();
            }
        }
    }

    private function studentsForSession(object $session): Collection
    {
        $teachingAssignment = $session->teachingAssignment;

        return Student::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereDate('exit_date', '>=', $this->attendanceDate))
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
                    ->onDate($this->attendanceDate);
            })
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Simpan presensi siswa.
     */
    public function saveAttendance(): void
    {
        try {
            $this->validate([
                'attendanceDate' => ['required', 'date'],
                'scheduleId' => ['required', 'exists:schedules,id'],
                'attendance' => ['required', 'array'],
                'attendance.*' => [
                    'required',
                    'in:present,late,excused,sick,absent',
                ],
                'notes' => ['array'],
                'notes.*' => ['nullable', 'string', 'max:1000'],
            ], [
                'attendanceDate.required' => 'Pilih tanggal presensi terlebih dahulu.',
                'attendanceDate.date' => 'Tanggal presensi tidak valid.',
                'scheduleId.required' => 'Pilih sesi KBM terlebih dahulu.',
                'scheduleId.exists' => 'Sesi KBM tidak tersedia. Muat ulang halaman.',
                'attendance.required' => 'Belum ada siswa yang dipresensi.',
                'attendance.array' => 'Daftar presensi tidak valid. Muat ulang sesi KBM.',
                'attendance.*.required' => 'Masih ada siswa yang belum dipresensi. Lengkapi statusnya secara manual sebelum menyimpan.',
                'attendance.*.in' => 'Ada status presensi yang tidak valid. Periksa kembali pilihan status siswa.',
                'notes.array' => 'Daftar catatan tidak valid. Muat ulang sesi KBM.',
                'notes.*.string' => 'Catatan siswa harus berupa teks.',
                'notes.*.max' => 'Catatan siswa maksimal 1.000 karakter.',
            ]);
        } catch (ValidationException $exception) {
            $this->dispatch('attendance-save-feedback', type: 'error', message: collect($exception->errors())->flatten()->first());

            throw $exception;
        }

        $session = $this->attendanceSessions
            ->firstWhere('id', $this->scheduleId);

        abort_unless($session, 403);

        // Schedule pertama menjadi anchor/identitas sesi.
        $schedule = $session->schedules->first();

        $studentIds = $this->studentsForSession($session)->modelKeys();
        $submittedIds = array_map('intval', array_keys($this->attendance));
        sort($studentIds);
        sort($submittedIds);
        if ($studentIds !== $submittedIds) {
            $message = 'Daftar siswa tidak sesuai kelas pada sesi ini. Muat ulang sesi KBM.';
            $this->addError('attendance', $message);
            $this->dispatch('attendance-save-feedback', type: 'error', message: $message);

            return;
        }

        DB::transaction(function () use ($schedule, $session, $submittedIds) {
            Student::whereIn('id', $submittedIds)->orderBy('id')->lockForUpdate()->get();
            $freshIds = $this->studentsForSession($session)->modelKeys();
            sort($freshIds);
            if ($freshIds !== $submittedIds) throw ValidationException::withMessages(['attendance' => 'Keanggotaan siswa berubah. Muat ulang sesi KBM.']);
            foreach ($this->attendance as $studentId => $status) {
                $record = StudentAttendance::query()
                    ->where('schedule_id', $schedule->id)
                    ->where('student_id', $studentId)
                    ->whereDate('attendance_date', $this->attendanceDate)
                    ->first() ?? new StudentAttendance([
                        'schedule_id' => $schedule->id,
                        'student_id' => $studentId,
                        'attendance_date' => $this->attendanceDate,
                    ]);
                $record->fill([
                    'status' => $status,
                    'notes' => $this->notes[$studentId] ?? null,
                    'recorded_by' => Auth::id(),
                ])->save();
            }
        });

        session()->flash(
            'success',
            'Presensi siswa berhasil disimpan.'
        );
        $this->dispatch('attendance-save-feedback', type: 'success', message: 'Presensi siswa berhasil disimpan.');
    }

    /**
     * Daftar siswa yang sedang ditampilkan.
     */
    public function getStudentsProperty(): Collection
    {
        if (!$this->scheduleId || empty($this->attendance)) {
            return collect();
        }

        $session = $this->selectedSession;

        return $session ? $this->studentsForSession($session) : collect();
    }

    public function getAttendanceSummaryProperty(): array
    {
        $statuses = $this->students->map(fn ($student) => $this->attendance[$student->id] ?? '');

        return [
            'total' => $statuses->count(),
            'present' => $statuses->filter(fn ($status) => in_array($status, ['present', 'late'], true))->count(),
            'pending' => $statuses->filter(fn ($status) => $status === '')->count(),
        ];
    }

    public function startScanner(): void
    {
        if (!$this->scheduleId) {
            session()->flash('error', 'Pilih sesi KBM terlebih dahulu.');

            return;
        }

        $this->scanMode = true;
        $this->scanMessage = 'Arahkan kamera ke QR code kartu siswa.';
        $this->scanMessageType = 'info';
    }

    public function stopScanner(): void
    {
        $this->scanMode = false;
        $this->scanMessage = '';
    }

    public function scanStudent(string $studentCode, ?int $sessionId = null, ?string $date = null): array
    {
        return DB::transaction(function () use ($studentCode, $sessionId, $date) {
            Student::where('student_code', trim($studentCode))->lockForUpdate()->first();
            return $this->scanStudentLocked($studentCode, $sessionId, $date);
        });
    }

    private function scanStudentLocked(string $studentCode, ?int $sessionId, ?string $date): array
    {
        $this->scanMessageType = 'error';
        if (!$this->scheduleId || ($sessionId !== null && $sessionId !== $this->scheduleId)
            || ($date !== null && $date !== $this->attendanceDate)) {
            $this->scanMessage = 'Sesi berubah. Buka kembali scanner pada sesi yang dipilih.';

            return ['success' => false, 'type' => 'error', 'message' => $this->scanMessage];
        }

        $session = $this->selectedSession;
        abort_unless($session, 403);
        $student = $this->studentsForSession($session)->firstWhere('student_code', trim($studentCode));

        if (!$student) {
            $this->scanMessage = 'QR code tidak terdaftar sebagai siswa aktif di kelas ini.';

            return ['success' => false, 'type' => 'error', 'message' => $this->scanMessage];
        }

        if (!array_key_exists($student->id, $this->attendance)) {
            $this->scanMessage =
                "{$student->full_name} bukan siswa pada kelas ini.";

            return ['success' => false, 'type' => 'error', 'message' => $this->scanMessage];
        }

        $record = StudentAttendance::query()
            ->where('schedule_id', $session->id)
            ->where('student_id', $student->id)
            ->whereDate('attendance_date', $this->attendanceDate)
            ->first();

        if ($record?->scanned_at) {
            $this->scanMessageType = 'info';
            $this->scanMessage = "{$student->full_name} sudah dipindai. Status tidak diubah.";

            return ['success' => false, 'type' => 'info', 'message' => $this->scanMessage];
        }

        $record ??= new StudentAttendance([
            'schedule_id' => $session->id,
            'student_id' => $student->id,
            'attendance_date' => $this->attendanceDate,
        ]);
        $record->fill(['status' => 'present', 'recorded_by' => Auth::id(), 'scanned_at' => now()])->save();

        $this->attendance[$student->id] = 'present';
        $this->scannedAt[$student->id] = $record->scanned_at->toIso8601String();

        $this->lastScannedStudentId = $student->id;

        $this->scanMessageType = 'success';
        $this->scanMessage = "{$student->full_name} — Hadir, berhasil disimpan.";

        return ['success' => true, 'type' => 'success', 'message' => $this->scanMessage];
    }
};
?>

<div class="space-y-6" x-data="attendanceSaveFeedback" x-on:attendance-save-feedback.window="notify($event.detail)">

    <div x-show="toast" x-transition.opacity style="display: none;"
        class="fixed inset-x-4 top-6 z-[60] mx-auto max-w-lg rounded-2xl border-2 p-5 shadow-xl"
        :class="toast?.type === 'success' ? 'border-green-300 bg-green-100 text-green-950' : 'border-red-300 bg-red-100 text-red-950'"
        role="alert" aria-live="assertive" aria-atomic="true">
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <div class="text-lg font-bold" x-text="toast?.type === 'success' ? 'Presensi berhasil disimpan' : 'Presensi belum tersimpan'"></div>
                <div class="mt-1 text-base" x-text="toast?.message"></div>
            </div>
            <button type="button" x-on:click="dismiss()" class="shrink-0 rounded-lg px-3 py-1 text-xl font-bold" aria-label="Tutup notifikasi">×</button>
        </div>
    </div>

    {{-- Header --}}
    <div>
        <flux:heading size="xl">
            Presensi Siswa
        </flux:heading>

        <flux:text class="mt-1">
            Presensi cukup dilakukan sekali untuk satu sesi KBM, termasuk pelajaran dua jam berturut-turut.
        </flux:text>
        <flux:button class="mt-3" icon="clock" :href="route(auth()->user()->activeRole().'.student-attendances.history')" wire:navigate>Riwayat Presensi Siswa</flux:button>
    </div>

    {{-- Success --}}
    @if (session('success'))
        <flux:callout variant="success">
            {{ session('success') }}
        </flux:callout>
    @endif

    @if (auth()->user()->canTeach() && ! auth()->user()->gtk_id)
        <flux:callout variant="warning">Akun Anda belum terhubung dengan data GTK. Hubungi administrator.</flux:callout>
    @endif
    @error('attendance')
        <flux:callout variant="warning">{{ $message }}</flux:callout>
    @enderror
    @if ($errors->has('attendance.*'))
        <flux:callout variant="warning">{{ $errors->first('attendance.*') }}</flux:callout>
    @endif
    @error('attendanceDate')
        <flux:callout variant="warning">{{ $message }}</flux:callout>
    @enderror

    {{-- Filter --}}
    <flux:card>
        <div class="grid gap-4 md:grid-cols-2">

            <flux:input type="date" wire:model.live="attendanceDate" label="Tanggal" />

            <flux:select wire:model.live="scheduleId" label="Sesi KBM">
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

    @if ($this->attendanceSessions->isEmpty() && auth()->user()->gtk_id)
        <flux:callout>Tidak ada jadwal mengajar aktif pada tanggal ini.</flux:callout>
    @endif

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

            <div class="mb-4 flex flex-wrap items-center justify-between gap-4">

                <div>
                    <flux:heading size="lg">
                        Daftar Siswa
                    </flux:heading>

                    <flux:text>
                        {{ $this->students->count() }} siswa
                    </flux:text>
                </div>

                <div class="flex flex-wrap gap-2">
                    <flux:button variant="outline" icon="qr-code" wire:click="startScanner"
                        x-data x-on:click="$store.studentScanSound.prepare()"
                        :disabled="!$this->selectedSession">
                        Scan QR code
                    </flux:button>

                    <flux:button variant="primary" wire:click="saveAttendance" wire:loading.attr="disabled">
                        Simpan Presensi
                    </flux:button>
                    @if (auth()->user()->canTeach() && $this->selectedSession && \Carbon\Carbon::parse($attendanceDate)->lte(today()))
                        <flux:button icon="book-open" :href="route('gtk.journals.index', ['date' => $attendanceDate]).'#kbm-'.$this->selectedSession->id">Lanjut ke Jurnal Mengajar</flux:button>
                    @endif
                </div>

            </div>

            <flux:text class="mb-4">
                Scan kartu untuk menandai hadir; hasil scan langsung tersimpan.
                Jika kartu tidak dibawa, pilih Hadir secara manual. Pilih Sakit, Izin, Terlambat, atau Alpa sesuai kondisi siswa,
                lalu tekan Simpan Presensi untuk menyimpan perubahan manual setelah semua siswa memiliki status.
            </flux:text>

            <div class="mb-4 flex flex-wrap gap-2" aria-live="polite">
                <flux:badge color="green">Hadir / terlambat: {{ $this->attendanceSummary['present'] }}</flux:badge>
                <flux:badge color="yellow">Belum dipresensi: {{ $this->attendanceSummary['pending'] }}</flux:badge>
                <flux:badge>Total: {{ $this->attendanceSummary['total'] }}</flux:badge>
            </div>

            @if ($scanMode)

                <flux:card wire:key="scanner-{{ $scheduleId }}-{{ $attendanceDate }}">

                    {{-- HANYA kamera yang wire:ignore --}}
                    <div wire:ignore x-data="studentQrScanner" x-init="start()">
                        <div x-show="toast" x-transition.opacity style="display: none;"
                            class="pointer-events-none fixed inset-x-4 top-6 z-50 mx-auto max-w-lg rounded-2xl border-2 p-5 shadow-xl"
                            :class="toast?.type === 'success' ? 'border-green-300 bg-green-100 text-green-950' : (toast?.type === 'info' ? 'border-blue-300 bg-blue-100 text-blue-950' : 'border-red-300 bg-red-100 text-red-950')"
                            role="status" aria-live="polite" aria-atomic="true">
                            <div class="flex items-start gap-3">
                                <span class="text-3xl font-bold" aria-hidden="true" x-text="toast?.type === 'success' ? '✓' : (toast?.type === 'info' ? 'ℹ' : '!')"></span>
                                <div>
                                    <div class="text-lg font-bold" x-text="toast?.type === 'success' ? 'Presensi berhasil' : (toast?.type === 'info' ? 'Sudah dipresensi' : 'Scan belum berhasil')"></div>
                                    <div class="mt-1 text-base font-medium" x-text="toast?.message"></div>
                                </div>
                            </div>
                        </div>
                        <video x-ref="video" class="max-h-80 w-full rounded-xl bg-black" autoplay muted playsinline aria-label="Kamera pemindai QR code siswa"></video>

                        <p x-show="cameraError" x-text="cameraError" role="alert" class="mt-3 text-sm text-red-600 dark:text-red-400"></p>
                        <p x-show="!cameraError" x-text="scanning ? 'Kamera aktif. Arahkan QR code kartu ke kamera.' : 'Menyiapkan kamera…'" class="mt-3 text-sm text-zinc-500"></p>

                        <button type="button" @click="stop(); $wire.stopScanner()" class="mt-3 rounded-lg border border-zinc-300 px-4 py-2 dark:border-zinc-600">
                            Tutup Scanner
                        </button>
                    </div>

                    @if ($lastScannedStudentId && ($lastStudent = $this->students->firstWhere('id', $lastScannedStudentId)))
                        <div class="mt-4 rounded-xl border border-green-300 bg-green-50 p-4 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100">
                            <div class="text-xs font-semibold uppercase tracking-wide">Scan terakhir berhasil tersimpan</div>
                            <div class="mt-1 text-lg font-bold">{{ $lastStudent->full_name }}</div>
                        </div>
                    @endif

                    {{-- BAGIAN INI JANGAN wire:ignore --}}
                    <div class="mt-3" role="status" aria-live="polite">
                        @if ($scanMessage)
                            <div @class([
                                'rounded-lg p-3',
                                'bg-green-50 text-green-800 dark:bg-green-950 dark:text-green-200' => $scanMessageType === 'success',
                                'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' => $scanMessageType === 'error',
                                'bg-blue-50 text-blue-800 dark:bg-blue-950 dark:text-blue-200' => $scanMessageType === 'info',
                            ])>
                                {{ $scanMessage }}
                            </div>
                        @endif
                    </div>

                </flux:card>

            @endif

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

                            <tr wire:key="student-{{ $student->id }}" @class([
                                'bg-green-50 dark:bg-green-950/40' => $lastScannedStudentId === $student->id,
                                'bg-amber-50/60 dark:bg-amber-950/20' => ($attendance[$student->id] ?? '') === '',
                            ])>

                                <td class="px-3 py-3">
                                    {{ $index + 1 }}
                                </td>

                                <td class="px-3 py-3 font-medium">
                                    {{ $student->full_name }}
                                    @if (isset($scannedAt[$student->id]))
                                        <div class="mt-1 text-xs font-normal text-zinc-500">Kartu sudah dipindai</div>
                                    @endif
                                </td>

                                <td class="px-3 py-3">
                                    {{ $student->nis ?? '-' }}
                                </td>

                                <td class="px-3 py-3">

                                    <flux:select wire:model.live="attendance.{{ $student->id }}" class="min-w-40" aria-label="Status presensi {{ $student->full_name }}">
                                        <option value="">Belum dipresensi</option>
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

                                    <flux:input wire:model="notes.{{ $student->id }}" placeholder="Catatan..." />

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
