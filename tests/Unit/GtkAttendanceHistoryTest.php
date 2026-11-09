<?php

use App\Models\Gtk;
use App\Models\GtkAbsenceRequest;
use App\Models\GtkAttendance;
use App\Models\GtkDailyApproval;
use App\Models\GtkWorkAudit;
use App\Models\GtkWorkJournal;
use App\Models\Holiday;
use App\Models\KbmDayPattern;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\GtkAttendanceHistoryService;
use App\Services\GtkAttendancePolicyService;
use App\Services\GtkDailyApprovalService;
use App\Services\GtkReportService;
use App\Services\KbmTimeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('History tests require an isolated in-memory database.');
    }
    foreach ([
        '0001_01_01_000000_create_users_table.php',
        '2025_08_14_170933_add_two_factor_columns_to_users_table.php',
        '2026_08_26_064935_create_gtks_table.php',
        '2026_09_08_042246_add_role_and_gtk_id_to_users_table.php', '2026_11_05_000001_add_secondary_role_to_users.php',
        '2026_08_26_081240_create_gtk_attendances_table.php',
        '2026_08_26_081832_create_school_settings_table.php',
        '2026_08_26_064302_create_lesson_periods_table.php',
        '2026_11_02_000004_add_daily_kbm_time_patterns.php',
        '2026_11_02_000005_normalize_lesson_period_sort_order.php',
        '2026_11_02_000006_add_gtk_attendance_time_references.php',
        '2026_09_10_081647_create_holidays_table.php',
        '2026_10_27_000003_create_gtk_work_journals.php',
        '2026_10_27_000004_create_gtk_daily_approvals.php',
        '2026_11_02_000001_create_gtk_absence_requests.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-11-03 15:00:00'));
    foreach (['staff', 'other', 'headmaster'] as $role) {
        $gtk = Gtk::create(['full_name' => 'GTK '.$role, 'is_active' => true]);
        $this->$role = User::factory()->create(['role' => $role === 'other' ? 'staff' : $role, 'gtk_id' => $gtk->id]);
    }
    $this->actingAs($this->staff);
    $this->service = app(GtkAttendanceHistoryService::class);
    $this->policy = app(GtkAttendancePolicyService::class);
    $this->data = ['correctionDate' => '2026-11-02', 'reason' => 'Lupa menekan tombol presensi pulang'];
});

it('shows the full month with absence, holidays and future dates without creating attendance', function () {
    Holiday::create(['date' => '2026-11-04', 'name' => 'Libur Madrasah', 'is_active' => true]);
    GtkAttendance::create(['gtk_id' => $this->other->gtk_id, 'attendance_date' => '2026-11-02', 'notes' => 'Catatan rahasia GTK lain']);
    $component = Livewire::test('gtk.attendance.history')->assertSee('Libur Minggu')->assertSee('Libur Madrasah')
        ->assertSee('Alpa')->assertSee('Belum waktunya')->assertDontSee('Catatan rahasia GTK lain')->assertDontSee('Menunggu approval')
        ->assertViewHas('days', fn ($days) => $days->count() === 30 && $days->first()['date']->toDateString() === '2026-11-01'
            && $days->last()['date']->toDateString() === '2026-11-30');
    foreach (range(1, 30) as $day) {
        $component->assertSee('id="attendance-day-2026-11-'.sprintf('%02d', $day).'"', false);
    }
    expect(GtkAttendance::where('gtk_id', $this->staff->gtk_id)->count())->toBe(0);
    $component->set('month', '2026-10')->assertHasNoErrors()->assertViewHas('days', fn ($days) => $days->count() === 31)
        ->set('month', '2024-02')->assertHasNoErrors()->assertViewHas('days', fn ($days) => $days->count() === 29);
});

it('validates the selected month without crashing', function (string $month) {
    Livewire::test('gtk.attendance.history')->set('month', $month)->assertHasErrors('month')
        ->assertViewHas('days', fn ($days) => $days->isEmpty())
        ->set('month', '2026-11')->assertHasNoErrors()->assertViewHas('days', fn ($days) => $days->count() === 30);
})->with(['', 'invalid', '2026-13', '2026-12', '2026-11-03']);

it('classifies lateness at the exact thirty and sixty minute boundaries', function (int $seconds, ?string $level) {
    $start = Carbon::parse('2026-11-02 07:00:00');
    $attendance = new GtkAttendance(['attendance_date' => '2026-11-02', 'status' => 'present',
        'check_in_at' => $start->copy()->addSeconds($seconds), 'check_out_at' => '2026-11-02 13:55:00']);
    $classification = $this->policy->evaluate(Carbon::parse('2026-11-02'), $attendance);
    expect($classification['late_level'])->toBe($level)->and($classification['late_minutes'])->toBe((int) ceil($seconds / 60))
        ->and($classification['early_level'])->toBeNull();
})->with([[0, null], [1, 'T1'], [1800, 'T1'], [1801, 'T2'], [3600, 'T2'], [3601, 'T3']]);

it('classifies early departure independently at the same boundaries', function (int $seconds, ?string $level) {
    $end = Carbon::parse('2026-11-02 13:55:00');
    $attendance = new GtkAttendance(['attendance_date' => '2026-11-02', 'status' => 'present',
        'check_in_at' => '2026-11-02 07:00:00', 'check_out_at' => $end->copy()->subSeconds($seconds)]);
    $classification = $this->policy->evaluate(Carbon::parse('2026-11-02'), $attendance);
    expect($classification['early_level'])->toBe($level)->and($classification['early_minutes'])->toBe((int) ceil($seconds / 60))
        ->and($classification['late_level'])->toBeNull();
})->with([[0, null], [1, 'PSW1'], [1800, 'PSW1'], [1801, 'PSW2'], [3600, 'PSW2'], [3601, 'PSW3']]);

it('uses each days KBM times and supports lateness plus early departure on one day', function () {
    foreach (range(1, 6) as $day) {
        KbmDayPattern::create(['day_of_week' => $day] + app(KbmTimeService::class)->defaults($day));
    }
    foreach (['2026-11-02' => '13:55', '2026-11-05' => '14:00', '2026-11-06' => '11:15'] as $date => $expected) {
        $context = $this->policy->context(Carbon::parse($date));
        expect($context['start']->format('H:i'))->toBe('07:00')->and($context['end']->format('H:i'))->toBe($expected);
    }
    $attendance = new GtkAttendance(['attendance_date' => '2026-11-02', 'status' => 'present',
        'check_in_at' => '2026-11-02 07:45', 'check_out_at' => '2026-11-02 13:10']);
    $classification = $this->policy->evaluate(Carbon::parse('2026-11-02'), $attendance);
    expect($classification['late_level'])->toBe('T2')->and($classification['early_level'])->toBe('PSW2');
});

it('accepts the seconds included in MySQL time values', function () {
    KbmDayPattern::create(['day_of_week' => 1] + array_replace(app(KbmTimeService::class)->defaults(1), [
        'morning_start' => '07:00:00', 'morning_end' => '07:30:00',
    ]));
    $context = $this->policy->context(Carbon::parse('2026-11-02'));
    expect($context['start']->format('H:i'))->toBe('07:00')->and($context['end']->format('H:i'))->toBe('13:55');
});

it('keeps captured working times when the KBM pattern later changes', function () {
    foreach (range(1, 6) as $day) {
        KbmDayPattern::create(['day_of_week' => $day] + app(KbmTimeService::class)->defaults($day));
    }
    $attendance = new GtkAttendance(['gtk_id' => $this->staff->gtk_id, 'attendance_date' => '2026-11-02', 'status' => 'present',
        'check_in_at' => '2026-11-02 07:00', 'check_out_at' => '2026-11-02 13:55']);
    $this->policy->captureExpectedTimes($attendance);
    $attendance->save();
    KbmDayPattern::where('day_of_week', 1)->firstOrFail()->update(app(KbmTimeService::class)->defaults(4));
    expect($this->policy->context(Carbon::parse('2026-11-02'))['end']->format('H:i'))->toBe('14:00');
    $classification = $this->policy->evaluate(Carbon::parse('2026-11-02'), $attendance->fresh());
    expect($classification['expected_end']->format('H:i'))->toBe('13:55')->and($classification['early_level'])->toBeNull();
});

it('marks a missing past checkout as PSW3 while keeping an ongoing day unpenalized', function () {
    $attendance = new GtkAttendance(['attendance_date' => '2026-11-02', 'status' => 'present', 'check_in_at' => '2026-11-02 07:00']);
    $classification = $this->policy->evaluate(Carbon::parse('2026-11-02'), $attendance);
    expect($classification['early_level'])->toBe('PSW3')->and($classification['early_minutes'])->toBeNull();
    $attendance->attendance_date = today();
    $attendance->check_in_at = today()->setTime(7, 0);
    expect($this->policy->evaluate(today(), $attendance)['early_level'])->toBeNull();
});

it('preserves PSW3 for a legacy manually filled forgotten checkout', function () {
    $attendance = new GtkAttendance(['attendance_date' => '2026-11-02', 'status' => 'present',
        'check_in_at' => '2026-11-02 07:00', 'check_out_at' => '2026-11-02 14:00', 'notes' => '[Lupa presensi pulang] Lupa']);
    expect($this->policy->evaluate(Carbon::parse('2026-11-02'), $attendance)['early_level'])->toBe('PSW3');
});

it('records a forgotten checkout without inventing a checkout time or changing LCKH approval', function () {
    $attendance = GtkAttendance::create(['gtk_id' => $this->staff->gtk_id, 'attendance_date' => '2026-11-02',
        'check_in_at' => '2026-11-02 07:03:12', 'check_in_latitude' => -7.5, 'check_in_accuracy' => 5, 'notes' => 'Catatan awal']);
    GtkDailyApproval::create(['gtk_id' => $this->staff->gtk_id, 'date' => '2026-11-02', 'status' => 'approved']);
    Livewire::test('gtk.attendance.history')->assertSee('PSW3')->call('openCorrection', '2026-11-02')->assertSee('Masuk tercatat: 07:03')
        ->set('reason', 'Lupa pulang setelah rapat')->call('saveForgotten')->assertHasNoErrors()->assertSet('correctionDate', null)
        ->assertSee('Lupa pulang setelah rapat')->assertDontSee('Menunggu approval');
    $attendance->refresh();
    expect($attendance->check_in_at->format('H:i:s'))->toBe('07:03:12')->and((float) $attendance->check_in_latitude)->toBe(-7.5)
        ->and($attendance->check_out_at)->toBeNull()->and($attendance->forgotten_checkout_at)->not->toBeNull()
        ->and($attendance->notes)->toContain('Catatan awal')->and(GtkDailyApproval::first()->status)->toBe('approved')
        ->and(GtkWorkAudit::where('event', 'attendance_forgotten')->first()->actor_id)->toBe($this->staff->id);
    expect(fn () => $this->service->saveForgotten($this->staff, $this->data))->toThrow(ValidationException::class);
});

it('requires a reason and a real check-in for forgotten checkout', function () {
    expect(fn () => $this->service->saveForgotten($this->staff, $this->data + ['gtk_id' => $this->other->gtk_id,
        'checkInTime' => '07:00', 'checkOutTime' => '14:00']))->toThrow(ValidationException::class);
    expect(GtkAttendance::count())->toBe(0);
    GtkAttendance::create(['gtk_id' => $this->staff->gtk_id, 'attendance_date' => '2026-11-02', 'check_in_at' => '2026-11-02 07:00']);
    Livewire::test('gtk.attendance.history')->call('openCorrection', '2026-11-02')->call('saveForgotten')->assertHasErrors('reason');
    expect(GtkAttendance::first()->forgotten_checkout_at)->toBeNull();
});

it('excludes Sundays holidays ongoing days and future dates from automatic absence', function () {
    Holiday::create(['date' => '2026-11-02', 'name' => 'Libur', 'is_active' => true]);
    foreach (['2026-11-01' => 'holiday', '2026-11-02' => 'holiday', '2026-11-03' => 'pending', '2026-11-04' => 'future', '2026-10-31' => 'absent'] as $date => $status) {
        expect($this->policy->evaluate(Carbon::parse($date), null)['status'])->toBe($status);
    }
});

it('gives recorded leave and sickness priority over automatic absence', function (string $status) {
    $attendance = GtkAttendance::create(['gtk_id' => $this->staff->gtk_id, 'attendance_date' => '2026-11-02', 'status' => $status]);
    $classification = $this->policy->evaluate(Carbon::parse('2026-11-02'), $attendance);
    expect($classification['status'])->toBe($status)->and($classification['late_level'])->toBeNull()->and($classification['early_level'])->toBeNull();
    expect(fn () => $this->service->saveForgotten($this->staff, $this->data))->toThrow(ValidationException::class);
})->with(['sick', 'leave', 'absent']);

it('blocks pending or approved absence requests from forgotten checkout', function (string $status) {
    GtkAttendance::create(['gtk_id' => $this->staff->gtk_id, 'attendance_date' => '2026-11-02', 'check_in_at' => '2026-11-02 07:00']);
    GtkAbsenceRequest::create(['gtk_id' => $this->staff->gtk_id, 'submitted_by' => $this->staff->id, 'type' => 'I',
        'description' => 'Izin', 'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'status' => $status, 'evidence_path' => 'proof.pdf']);
    expect(fn () => $this->service->saveForgotten($this->staff, $this->data))->toThrow(ValidationException::class);
    expect($this->service->days($this->staff, '2026-11')->first(fn ($row) => $row['date']->toDateString() === '2026-11-02')['can_correct'])->toBeFalse();
})->with(['pending', 'approved']);

it('rejects current future malformed and already completed dates', function (string $date) {
    expect(fn () => $this->service->saveForgotten($this->staff, array_replace($this->data, ['correctionDate' => $date])))
        ->toThrow(ValidationException::class);
})->with(['2026-11-03', '2026-11-04', 'invalid']);

it('rechecks completion and identity before saving and clears the form on month changes', function () {
    $attendance = GtkAttendance::create(['gtk_id' => $this->staff->gtk_id, 'attendance_date' => '2026-11-02', 'check_in_at' => '2026-11-02 07:00']);
    $component = Livewire::test('gtk.attendance.history')->call('openCorrection', '2026-11-02')->set('reason', 'Lupa')
        ->set('month', '2026-10')->assertSet('correctionDate', null)->assertSet('reason', '');
    $component->set('month', '2026-11')->call('openCorrection', '2026-11-02')->set('reason', 'Lupa');
    $attendance->update(['check_out_at' => '2026-11-02 14:00']);
    $component->call('saveForgotten')->assertHasErrors('attendance');
    expect($attendance->fresh()->forgotten_checkout_at)->toBeNull();
});

it('denies corrections to inactive or unlinked accounts', function () {
    $this->staff->gtk->update(['is_active' => false]);
    expect(fn () => $this->service->saveForgotten($this->staff, $this->data))->toThrow(HttpException::class);
    $unlinked = User::factory()->create(['role' => 'staff', 'gtk_id' => null]);
    $this->actingAs($unlinked)->get(route('gtk.attendance.history'))->assertForbidden();
    expect(fn () => $this->service->saveForgotten($unlinked, $this->data))->toThrow(HttpException::class);
});

it('requires LCKH before GPS departure and still calculates both grades', function () {
    SchoolSetting::create(['school_name' => 'Madrasah', 'latitude' => -7.5, 'longitude' => 109.5, 'attendance_radius' => 100]);
    $this->travelTo(Carbon::parse('2026-11-03 07:20'));
    $component = Livewire::test('gtk.attendance.index')->call('checkIn', -7.5, 109.5, 5)->assertSet('errorMessage', '');
    $component->call('checkOut', -7.5, 109.5, 5)->assertSet('errorMessage', fn ($message) => str_contains($message, 'LCKH hari ini belum lengkap.'));
    expect(GtkAttendance::first()->check_out_at)->toBeNull();
    app(\App\Services\GtkJournalService::class)->save($this->staff, today(), 'lckh', ['status' => 'completed', 'activities' => 'Tugas administrasi']);
    $this->travelTo(Carbon::parse('2026-11-03 13:35'));
    $component->call('checkOut', -7.5, 109.5, 5)->assertSet('errorMessage', '');
    $attendance = GtkAttendance::firstOrFail();
    $classification = $this->policy->evaluate(today(), $attendance);
    expect($attendance->status)->toBe('late')->and($attendance->scheduled_check_in_at->format('H:i'))->toBe('07:00')
        ->and($attendance->scheduled_check_out_at->format('H:i'))->toBe('13:55')->and($classification['late_level'])->toBe('T1')
        ->and($classification['early_level'])->toBe('PSW1')->and(GtkWorkJournal::count())->toBe(1);
});

it('shows automatic absence and grade filters in admin monitoring without inserting artificial attendance', function () {
    GtkAttendance::create(['gtk_id' => $this->other->gtk_id, 'attendance_date' => '2026-11-02',
        'check_in_at' => '2026-11-02 08:15', 'check_out_at' => '2026-11-02 12:00']);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);
    Livewire::test('admin.gtk-attendances.index')->set('date', '2026-11-02')->assertViewHas('absentCount', 2)
        ->assertViewHas('lateCount', 1)->set('status', 't3')->assertViewHas('attendances', fn ($rows) => $rows->total() === 1)
        ->assertSee('PSW3')->set('status', 'absent')->assertViewHas('attendances', fn ($rows) => $rows->total() === 2);
    expect(GtkAttendance::count())->toBe(1);
});

it('prints a complete monthly attendance recap with grades and no approval column', function () {
    GtkAttendance::create(['gtk_id' => $this->staff->gtk_id, 'attendance_date' => '2026-11-02',
        'check_in_at' => '2026-11-02 07:31', 'check_out_at' => '2026-11-02 13:00']);
    $data = app(GtkReportService::class)->data($this->staff->gtk, today(), 'attendance');
    expect($data['rows'])->toHaveCount(30)->and($data['rows'][1]['classification']['late_level'])->toBe('T2');
    $html = view('pdf.gtk.report', $data)->render();
    expect($html)->toContain('T2')->toContain('PSW2')->not->toContain('>Approval<');
});

it('approves completed LCKH without attendance and keeps approval when attendance changes', function () {
    GtkWorkJournal::create(['gtk_id' => $this->staff->gtk_id, 'journal_date' => '2026-11-02', 'session_key' => 'lckh',
        'kind' => 'daily', 'label' => 'LCKH', 'activities' => 'Pelayanan administrasi', 'status' => 'completed']);
    app(GtkDailyApprovalService::class)->decide($this->headmaster, $this->staff->gtk_id, Carbon::parse('2026-11-02'), ['status' => 'approved']);
    GtkAttendance::create(['gtk_id' => $this->staff->gtk_id, 'attendance_date' => '2026-11-02', 'check_in_at' => '2026-11-02 07:00']);
    expect(GtkDailyApproval::first()->status)->toBe('approved');
    $this->actingAs($this->headmaster)->get(route('headmaster.work-reviews.show', [$this->staff->gtk_id, 'month' => '2026-11']))
        ->assertOk()->assertSee('Pelayanan administrasi')->assertDontSee('Masuk / pulang')->assertDontSee('Download Presensi');
});

it('lets staff monitor all GTK attendance while approval and master data remain restricted', function () {
    GtkAttendance::create(['gtk_id' => $this->other->gtk_id, 'attendance_date' => '2026-11-02',
        'check_in_at' => '2026-11-02 08:15', 'check_out_at' => '2026-11-02 12:00']);
    $this->actingAs($this->staff);
    $this->get(route('staff.gtk-attendances.index'))->assertOk()->assertSee('Monitoring Presensi GTK');
    Livewire::test('admin.gtk-attendances.index')->set('date', '2026-11-02')
        ->assertViewHas('lateCount', 1)->set('gtkId', (string) $this->other->gtk_id)
        ->assertViewHas('attendances', fn ($rows) => $rows->total() === 1);
    $this->get(route('admin.gtks.index'))->assertForbidden();
    $this->get(route('headmaster.absences.index'))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'teacher']));
    $this->get(route('staff.gtk-attendances.index'))->assertForbidden();
    Livewire::test('admin.gtk-attendances.index')->assertForbidden();
});
