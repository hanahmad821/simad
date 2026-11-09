<?php

use App\Services\StudentAttendanceHistoryService;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $fromDate = '';

    #[Url]
    public string $toDate = '';

    #[Url]
    public string $schoolClassId = '';

    #[Url]
    public string $subjectId = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $search = '';

    #[Locked]
    public ?int $selectedScheduleId = null;

    #[Locked]
    public string $selectedDate = '';

    public bool $showSessionModal = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasActiveRole(['admin', 'staff', 'teacher', 'headmaster']), 403);
        $this->fromDate = $this->fromDate ?: today()->startOfMonth()->toDateString();
        $this->toDate = $this->toDate ?: today()->toDateString();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['fromDate', 'toDate', 'schoolClassId', 'subjectId', 'status', 'search'], true)) {
            $this->resetPage();
            $this->resetValidation();
            $this->closeSession();
        }
    }

    public function resetFilters(): void
    {
        $this->fromDate = today()->startOfMonth()->toDateString();
        $this->toDate = today()->toDateString();
        $this->reset('schoolClassId', 'subjectId', 'status', 'search');
        $this->resetPage();
        $this->resetValidation();
        $this->closeSession();
    }

    public function openSession(int $scheduleId, string $date): void
    {
        abort_unless(validator(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->passes(), 404);
        abort_if(app(StudentAttendanceHistoryService::class)->sessionRecords(auth()->user(), $scheduleId, $date)->isEmpty(), 404);
        $this->selectedScheduleId = $scheduleId;
        $this->selectedDate = $date;
        $this->showSessionModal = true;
    }

    public function closeSession(): void
    {
        $this->showSessionModal = false;
        $this->selectedScheduleId = null;
        $this->selectedDate = '';
    }

    public function render()
    {
        $service = app(StudentAttendanceHistoryService::class);
        $filters = $this->only(['fromDate', 'toDate', 'schoolClassId', 'subjectId', 'status', 'search']);
        $validator = validator($filters, [
            'fromDate' => ['required', 'date_format:Y-m-d'],
            'toDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:fromDate'],
            'schoolClassId' => ['nullable', 'integer', 'min:1'],
            'subjectId' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:present,late,excused,sick,absent'],
            'search' => ['nullable', 'string', 'max:150'],
        ], [
            'fromDate.required' => 'Isi tanggal awal.', 'fromDate.date_format' => 'Tanggal awal tidak valid.',
            'toDate.required' => 'Isi tanggal akhir.', 'toDate.date_format' => 'Tanggal akhir tidak valid.',
            'toDate.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            'schoolClassId.integer' => 'Pilihan kelas tidak valid.', 'subjectId.integer' => 'Pilihan mata pelajaran tidak valid.',
            'schoolClassId.min' => 'Pilihan kelas tidak valid.', 'subjectId.min' => 'Pilihan mata pelajaran tidak valid.',
            'status.in' => 'Pilihan status tidak valid.', 'search.max' => 'Pencarian maksimal 150 karakter.',
        ]);
        $valid = $validator->passes();
        if (! $valid) {
            $this->setErrorBag($validator->errors());
        }

        return $this->view([
            'sessions' => $valid ? $service->sessions(auth()->user(), $filters)->paginate(12) : null,
            'summary' => $valid ? $service->summary(auth()->user(), $filters) : [],
            'classes' => $service->classes(auth()->user()),
            'subjects' => $service->subjects(auth()->user()),
            'statusLabels' => StudentAttendanceHistoryService::STATUSES,
            'detailRecords' => $this->showSessionModal && $this->selectedScheduleId
                ? $service->sessionRecords(auth()->user(), $this->selectedScheduleId, $this->selectedDate) : collect(),
        ]);
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">Riwayat Presensi Siswa</flux:heading>
        <flux:text class="mt-1">Periksa kehadiran per sesi pelajaran. Pelajaran dua jam berturut-turut dihitung sebagai satu sesi.</flux:text>
    </div>

    @if (auth()->user()->isTeacher() && ! auth()->user()->gtk_id)
        <flux:callout variant="warning">Akun Anda belum terhubung dengan data GTK. Hubungi administrator untuk melihat riwayat mengajar Anda.</flux:callout>
    @endif

    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <flux:input type="date" label="Tanggal awal" wire:model.live="fromDate" />
            <flux:input type="date" label="Tanggal akhir" wire:model.live="toDate" />
            <flux:select label="Kelas" wire:model.live="schoolClassId">
                <option value="">Semua kelas</option>
                @foreach ($classes as $class)<option value="{{ $class->id }}">{{ $class->name }}</option>@endforeach
            </flux:select>
            <flux:select label="Mata pelajaran" wire:model.live="subjectId">
                <option value="">Semua mata pelajaran</option>
                @foreach ($subjects as $subject)<option value="{{ $subject->id }}">{{ $subject->name }}</option>@endforeach
            </flux:select>
            <flux:select label="Status" wire:model.live="status">
                <option value="">Semua status</option>
                @foreach ($statusLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </flux:select>
            <flux:input label="Cari siswa" placeholder="Nama, NIS, atau NISN" icon="magnifying-glass" wire:model.live.debounce.300ms="search" />
        </div>
        <flux:button class="mt-4" variant="ghost" icon="arrow-path" wire:click="resetFilters">Reset filter</flux:button>
        @if ($errors->any())
            <div class="mt-3" role="alert"><flux:callout variant="warning">{{ $errors->first() }}</flux:callout></div>
        @endif
    </div>

    @if ($sessions)
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>Sesi</flux:text><div class="mt-1 text-2xl font-semibold">{{ number_format($sessions->total(), 0, ',', '.') }}</div>
            </div>
            @foreach ($statusLabels as $value => $label)
                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text>{{ $label }}</flux:text><div class="mt-1 text-2xl font-semibold">{{ number_format($summary[$value] ?? 0, 0, ',', '.') }}</div>
                </div>
            @endforeach
        </div>
        <flux:text>{{ number_format(array_sum($summary), 0, ',', '.') }} catatan kehadiran per sesi. Ringkasan dan daftar mengikuti filter yang dipilih.</flux:text>
        <flux:callout icon="information-circle">Riwayat berisi data yang sudah tersimpan. Sesi yang baru dipindai sebagian juga tampil; siswa yang belum dicatat tidak otomatis dianggap alpa.</flux:callout>

        <div class="grid items-start gap-4 lg:grid-cols-2" wire:loading.class="opacity-60">
            @forelse ($sessions as $session)
                @php($assignment = $session->schedule?->teachingAssignment)
                <article wire:key="session-{{ $session->schedule_id }}-{{ $session->session_date }}" class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">{{ $assignment?->schoolClass?->name ?? 'Kelas tidak tersedia' }}</flux:heading>
                            <div class="mt-1 font-medium">{{ $assignment?->subject?->name ?? 'Mata pelajaran tidak tersedia' }}</div>
                        </div>
                        <flux:badge>{{ \Carbon\Carbon::parse($session->session_date)->locale('id')->translatedFormat('d F Y') }}</flux:badge>
                    </div>
                    <flux:text class="mt-3">Guru: {{ $assignment?->gtk?->full_name ?? '—' }}</flux:text>
                    <flux:text>Awal sesi: {{ $session->schedule?->lessonPeriod?->name ?? '—' }}</flux:text>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($statusLabels as $value => $label)
                            <flux:badge :color="match($value) { 'present' => 'green', 'late' => 'yellow', 'sick' => 'purple', 'excused' => 'blue', default => 'red' }">{{ $label }}: {{ $session->{$value.'_count'} }}</flux:badge>
                        @endforeach
                    </div>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <flux:text>{{ $session->total }} catatan sesuai filter</flux:text>
                        <flux:button size="sm" wire:click="openSession({{ $session->schedule_id }}, '{{ $session->session_date }}')">Detail sesi</flux:button>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center lg:col-span-2 dark:border-zinc-600">
                    <flux:heading>Belum ada riwayat yang sesuai</flux:heading>
                    <flux:text class="mt-2">Coba ubah rentang tanggal atau filter lainnya.</flux:text>
                </div>
            @endforelse
        </div>
        {{ $sessions->links() }}
    @endif

    <flux:modal wire:model="showSessionModal" class="md:w-4xl">
        <div class="space-y-5">
            <flux:heading size="lg">Detail Sesi Presensi</flux:heading>
            @if ($detailRecords->isNotEmpty())
                @php($detailAssignment = $detailRecords->first()->schedule?->teachingAssignment)
                <div>
                    <div class="font-semibold">{{ $detailAssignment?->schoolClass?->name }} · {{ $detailAssignment?->subject?->name }}</div>
                    <flux:text>{{ \Carbon\Carbon::parse($selectedDate)->locale('id')->translatedFormat('l, d F Y') }} · {{ $detailAssignment?->gtk?->full_name }}</flux:text>
                    <flux:text>{{ $detailAssignment?->academicYear?->name }}</flux:text>
                    <flux:text>Awal sesi: {{ $detailRecords->first()->schedule?->lessonPeriod?->name ?? '—' }} · {{ substr($detailRecords->first()->schedule?->lessonPeriod?->start_time ?? '', 0, 5) ?: '—' }}</flux:text>
                </div>
                <flux:text>Seluruh {{ $detailRecords->count() }} catatan tersimpan pada sesi ini, termasuk status di luar filter daftar.</flux:text>
                <div class="max-h-[60vh] overflow-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800"><tr class="text-left">
                            <th class="p-3">Siswa</th><th class="p-3">Status</th><th class="p-3">Catatan</th><th class="p-3">Pindai kartu</th><th class="p-3">Pencatat</th>
                        </tr></thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($detailRecords as $record)
                                <tr wire:key="history-record-{{ $record->id }}">
                                    <td class="min-w-40 p-3"><div class="font-medium">{{ $record->student?->full_name ?? 'Siswa tidak tersedia' }}</div><div class="mt-1 text-xs text-zinc-500">NIS: {{ $record->student?->nis ?: '—' }}</div></td>
                                    <td class="p-3"><flux:badge :color="match($record->status) { 'present' => 'green', 'late' => 'yellow', 'sick' => 'purple', 'excused' => 'blue', default => 'red' }">{{ $statusLabels[$record->status] ?? $record->status }}</flux:badge></td>
                                    <td class="min-w-40 max-w-xs break-words p-3">{{ $record->notes ?: '—' }}</td>
                                    <td class="whitespace-nowrap p-3">{{ $record->scanned_at?->format('H:i:s') ?? 'Tidak ada' }}</td>
                                    <td class="min-w-32 p-3">{{ $record->recordedBy?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <flux:text>Data sesi tidak tersedia.</flux:text>
            @endif
            <div class="flex justify-end"><flux:button wire:click="closeSession">Tutup</flux:button></div>
        </div>
    </flux:modal>
</div>
