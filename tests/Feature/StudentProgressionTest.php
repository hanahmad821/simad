<?php

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\User;
use App\Services\StudentLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2027, 6, 30));
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
    $this->year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $this->next = AcademicYear::create(['name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_active' => false]);
    $this->source = SchoolClass::create(['name' => 'X A', 'grade' => 10, 'capacity' => 30, 'academic_year_id' => $this->year->id]);
    $this->target = SchoolClass::create(['name' => 'XI A', 'grade' => 11, 'capacity' => 30, 'academic_year_id' => $this->next->id]);
    $this->student = Student::create(['student_code' => 'KEEP-QR', 'full_name' => 'Siswa Satu', 'nis' => '123', 'is_active' => true]);
    $this->history = StudentClassHistory::create(['student_id' => $this->student->id, 'school_class_id' => $this->source->id, 'academic_year_id' => $this->year->id, 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);
    $this->data = ['kind' => 'year_end', 'source_class_id' => $this->source->id, 'date' => '2027-06-30',
        'rows' => [['student_id' => $this->student->id, 'action' => 'promote', 'target_class_id' => $this->target->id]]];
    $this->service = app(StudentLifecycleService::class);
    $this->process = function (array $data) {
        $plan = $this->service->preview($this->admin, $data);
        return $this->service->commit($this->admin, $data, (string) Str::uuid(), $plan['fingerprint']);
    };
});

it('renders all management pages with annual context', function () {
    foreach ([
        route('admin.student-progressions.index'),
        route('admin.student-progressions.index', ['source_class_id' => $this->source->id]),
        route('admin.students.status', $this->student),
        route('admin.classes.index'), route('admin.classes.create'),
        route('admin.classes.show', $this->source), route('admin.classes.edit', $this->source),
        route('admin.classes.placement', $this->source),
        route('admin.classes.placement', ['class' => $this->source, 'mode' => 'move']),
        route('admin.schedules.assignments'), route('admin.academic-years.index'),
        route('admin.student-management.history'), route('admin.student-management.issues'),
    ] as $url) $this->get($url)->assertOk();
});

it('requires review and commits idempotently while preserving old membership and QR', function () {
    $response = $this->post(route('admin.student-management.preview'), $this->data)->assertRedirect()->assertSessionHasNoErrors();
    $url = $response->headers->get('Location');
    $this->get($url)->assertOk()->assertSee('Siswa Satu');
    expect(StudentClassHistory::count())->toBe(1);
    $this->post($url)->assertRedirect()->assertSessionHasNoErrors();
    $this->post($url)->assertRedirect()->assertSessionHasNoErrors();
    expect(StudentClassHistory::count())->toBe(2)->and(DB::table('student_year_decisions')->count())->toBe(1)
        ->and(DB::table('student_management_events')->count())->toBe(1)->and($this->history->fresh()->is_active)->toBeTrue()
        ->and($this->student->fresh()->student_code)->toBe('KEEP-QR')->and($this->year->fresh()->is_active)->toBeTrue();
    $this->get(route('admin.student-management.history'))->assertOk()->assertSee('Siswa Satu');
});

it('persists deferral and can later finalize it', function () {
    $data = $this->data;
    $data['rows'][0] = ['student_id' => $this->student->id, 'action' => 'defer', 'reason' => 'Menunggu rapat'];
    ($this->process)($data);
    expect(DB::table('student_year_decisions')->value('action'))->toBe('defer')->and(StudentClassHistory::count())->toBe(1);
    $this->get(route('admin.student-progressions.index', ['source_class_id' => $this->source->id]))->assertOk()->assertSee('Menunggu rapat');
    ($this->process)($this->data);
    expect(DB::table('student_year_decisions')->value('action'))->toBe('promote')->and(DB::table('student_management_events')->count())->toBe(2);
});

it('rejects missing choices invalid destinations and repeated final decisions', function (string $case) {
    if ($case === 'duplicate') ($this->process)($this->data);
    if ($case === 'empty') $this->data['rows'] = [];
    if ($case === 'grade') $this->target->update(['grade' => 12]);
    if ($case === 'year') $this->target->update(['academic_year_id' => $this->year->id]);
    if ($case === 'inactive') $this->target->update(['is_active' => false]);
    if ($case === 'defer') $this->data['rows'][0]['action'] = 'defer';
    if ($case === 'graduate') $this->data['rows'][0]['action'] = 'graduate';
    expect(fn () => ($this->process)($this->data))->toThrow(ValidationException::class);
})->with(['empty', 'grade', 'year', 'inactive', 'duplicate', 'defer', 'graduate']);

it('checks batch capacity including future reservations without partial writes', function () {
    $this->target->update(['capacity' => 1]);
    $second = Student::create(['student_code' => 'TWO', 'full_name' => 'Siswa Dua']);
    StudentClassHistory::create(['student_id' => $second->id, 'school_class_id' => $this->source->id, 'academic_year_id' => $this->year->id, 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);
    $this->data['rows'][] = ['student_id' => $second->id, 'action' => 'promote', 'target_class_id' => $this->target->id];
    expect(fn () => ($this->process)($this->data))->toThrow(ValidationException::class);
    expect(DB::table('student_year_decisions')->count())->toBe(0)->and(StudentClassHistory::count())->toBe(2);
});

it('rejects a stale review while retaining user choices', function () {
    $response = $this->post(route('admin.student-management.preview'), $this->data);
    $this->target->update(['name' => 'XI Baru']);
    $this->post($response->headers->get('Location'))->assertRedirect()->assertSessionHasErrors('management');
    expect(DB::table('student_year_decisions')->count())->toBe(0);
});

it('moves within a year using nonoverlapping dated memberships', function () {
    $other = SchoolClass::create(['name' => 'X B', 'grade' => 10, 'academic_year_id' => $this->year->id]);
    ($this->process)(['kind' => 'move', 'source_class_id' => $this->source->id, 'target_class_id' => $other->id,
        'student_ids' => [$this->student->id], 'date' => '2027-01-01', 'reason' => 'Penyesuaian rombel']);
    expect($this->history->fresh()->ends_on->toDateString())->toBe('2026-12-31');
    expect($this->student->classHistories()->onDate('2026-12-31')->value('school_class_id'))->toBe($this->source->id)
        ->and($this->student->classHistories()->onDate('2027-01-01')->value('school_class_id'))->toBe($other->id);
});

it('allows initial bulk placement but prevents reusing it for continuation', function () {
    $new = Student::create(['student_code' => 'NEW', 'full_name' => 'Siswa Baru']);
    ($this->process)(['kind' => 'enroll', 'target_class_id' => $this->source->id, 'student_ids' => [$new->id], 'date' => '2027-01-01']);
    expect($new->classHistories()->count())->toBe(1);
    expect(fn () => ($this->process)(['kind' => 'enroll', 'target_class_id' => $this->target->id, 'student_ids' => [$new->id], 'date' => '2027-07-01']))->toThrow(ValidationException::class);
});

it('archives a student without losing history and allows biodata edits without reactivation', function (string $action, string $status) {
    if ($action === 'graduate') {
        $this->source->update(['grade' => 12]); $data = $this->data; $data['rows'][0]['action'] = $action;
    } else $data = ['kind' => 'status', 'student_ids' => [$this->student->id], 'action' => $action,
        'reason' => 'Permohonan orang tua', 'destination_school' => 'Sekolah Tujuan', 'date' => '2027-06-30'];
    ($this->process)($data);
    expect($this->student->fresh()->exit_status)->toBe($status)->and($this->student->fresh()->is_active)->toBeFalse();
    Livewire::test('admin.students.index')->call('editStudent', $this->student->id)->assertHasNoErrors()
        ->set('fullName', 'Biodata diperbaiki')->set('isActive', true)->call('saveStudent')->assertHasNoErrors();
    expect($this->student->fresh()->is_active)->toBeFalse()->and($this->student->fresh()->full_name)->toBe('Biodata diperbaiki')
        ->and($this->student->classHistories()->count())->toBe(1);
})->with([['graduate', 'graduated'], ['transfer', 'transferred'], ['withdraw', 'withdrawn']]);

it('cancels future placement when a student exits and audits cancellation', function () {
    ($this->process)($this->data);
    ($this->process)(['kind' => 'status', 'student_ids' => [$this->student->id], 'action' => 'withdraw', 'reason' => 'Keluar', 'date' => '2027-06-30']);
    expect($this->student->classHistories()->where('academic_year_id', $this->next->id)->first()->voided_at)->not->toBeNull();
});

it('corrects a final result with an append-only audit', function () {
    ($this->process)($this->data);
    $event = DB::table('student_management_events')->first();
    ($this->process)(['kind' => 'correct', 'event_id' => $event->id, 'date' => '2027-06-30', 'reason' => 'Salah memilih tujuan']);
    expect(DB::table('student_management_events')->count())->toBe(2)->and(DB::table('student_year_decisions')->count())->toBe(0)
        ->and($this->student->classHistories()->whereNull('voided_at')->count())->toBe(1);
    ($this->process)($this->data);
    expect(DB::table('student_year_decisions')->count())->toBe(1);
});

it('requires explicit activation and reasons for unfinished preparations', function () {
    $this->post(route('admin.student-progressions.years.activate'), ['year_id' => $this->next->id, 'confirmed' => 1])->assertSessionHasErrors('reason');
    $this->post(route('admin.student-progressions.years.activate'), ['year_id' => $this->next->id, 'confirmed' => 1, 'reason' => 'Jadwal dilengkapi bertahap'])->assertSessionHasNoErrors();
    expect($this->next->fresh()->is_active)->toBeTrue()->and($this->year->fresh()->is_active)->toBeFalse();
});

it('reconciles ambiguous legacy memberships with review and retains original records', function () {
    $this->history->update(['starts_on' => null, 'ends_on' => null]);
    $issue = DB::table('student_data_issues')->insertGetId(['student_id' => $this->student->id, 'academic_year_id' => $this->year->id, 'description' => 'Tanggal tidak pasti', 'created_at' => now(), 'updated_at' => now()]);
    $this->get(route('admin.student-management.reconcile', $issue))->assertOk();
    expect(fn () => ($this->process)($this->data))->toThrow(ValidationException::class);
    ($this->process)(['kind' => 'reconcile', 'issue_id' => $issue, 'date' => '2027-06-30', 'reason' => 'Berdasarkan buku induk',
        'intervals' => [['id' => $this->history->id, 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']]]);
    expect(DB::table('student_data_issues')->value('resolved_at'))->not->toBeNull();
    ($this->process)($this->data);
    expect(StudentClassHistory::count())->toBe(2);
});

it('blocks staff from lifecycle endpoints and direct service access', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff)->get(route('admin.student-progressions.index'))->assertForbidden();
    $this->post(route('admin.student-management.preview'), $this->data)->assertForbidden();
    expect(fn () => $this->service->preview($staff, $this->data))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
