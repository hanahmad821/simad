<?php

use App\Models\AcademicYear;
use App\Models\Gtk;
use App\Models\GtkAttendance;
use App\Models\GtkDailyApproval;
use App\Models\GtkKbmException;
use App\Models\GtkWorkAudit;
use App\Models\GtkWorkJournal;
use App\Models\Holiday;
use App\Models\LessonPeriod;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\SchoolSetting;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentClassHistory;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\GtkDailyApprovalService;
use App\Services\GtkJournalService;
use App\Services\GtkReportService;
use App\Services\ScheduleSessionService;
use App\Services\StudentAttendanceSessionService;
use Carbon\Carbon;
use Database\Seeders\KbmTimeSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Journal tests require an isolated in-memory database.');
    }
    foreach ([
        '0001_01_01_000000_create_users_table.php',
        '2025_08_14_170933_add_two_factor_columns_to_users_table.php',
        '2026_08_26_064935_create_gtks_table.php',
        '2026_09_08_042246_add_role_and_gtk_id_to_users_table.php', '2026_11_05_000001_add_secondary_role_to_users.php',
        '2026_08_26_020757_create_academic_years_table.php',
        '2026_08_26_021543_create_semesters_table.php',
        '2026_08_26_025138_create_school_classes_table.php',
        '2026_08_26_025918_create_subjects_table.php',
        '2026_08_26_064302_create_lesson_periods_table.php',
        '2026_11_02_000004_add_daily_kbm_time_patterns.php',
        '2026_11_02_000005_normalize_lesson_period_sort_order.php',
        '2026_08_26_064539_create_rooms_table.php',
        '2026_08_26_071443_create_teaching_assignments_table.php',
        '2026_08_26_073224_create_schedules_table.php',
        '2026_08_26_074302_create_students_table.php',
        '2026_08_26_075134_create_student_class_histories_table.php',
        '2026_08_26_075839_create_student_attendances_table.php',
        '2026_08_26_081240_create_gtk_attendances_table.php',
        '2026_11_02_000006_add_gtk_attendance_time_references.php',
        '2026_08_26_081832_create_school_settings_table.php',
        '2026_09_10_081647_create_holidays_table.php',
        '2026_10_27_000003_create_gtk_work_journals.php',
        '2026_10_27_000004_create_gtk_daily_approvals.php',
        '2026_11_02_000002_make_kbm_independent_of_semesters.php',
        '2026_10_27_000002_add_homeroom_teacher_to_school_classes.php',
        '2026_11_02_000003_add_room_to_school_classes.php',
        '2026_11_04_000001_add_student_progression.php',
        '2026_11_04_000002_restructure_student_management.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-10-26 14:00:00'));
    $this->service = app(GtkJournalService::class);
    $year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $room = Room::create(['code' => 'R1', 'name' => 'Ruang 1']);
    $subject = Subject::create(['code' => 'MTK', 'name' => 'Matematika']);
    $class = SchoolClass::create(['name' => 'VII A', 'grade' => 7]);
    foreach (['teacher', 'staff', 'headmaster', 'other'] as $role) {
        $gtk = Gtk::create(['full_name' => 'GTK '.$role]);
        $this->$role = User::factory()->create(['role' => $role === 'other' ? 'teacher' : $role, 'gtk_id' => $gtk->id]);
    }
    $assignment = TeachingAssignment::create(['gtk_id' => $this->teacher->gtk_id, 'school_class_id' => $class->id,
        'subject_id' => $subject->id, 'academic_year_id' => $year->id]);
    foreach ([1, 2, 4] as $number) {
        $period = LessonPeriod::create(['period_number' => $number, 'name' => 'JP '.$number, 'start_time' => '07:00', 'end_time' => '07:45']);
        Schedule::create(['teaching_assignment_id' => $assignment->id, 'lesson_period_id' => $period->id, 'room_id' => $room->id, 'day_of_week' => 1]);
    }
    SchoolSetting::create(['school_name' => 'Madrasah', 'latitude' => -7.5, 'longitude' => 109.5, 'attendance_radius' => 100]);
    $this->actingAs($this->teacher);
    $this->keys = $this->service->sessions($this->teacher->gtk_id, today())->keys();
    $this->student = Student::create(['student_code' => 'JRN-1', 'full_name' => 'Siswa Jurnal']);
    StudentClassHistory::create(['student_id' => $this->student->id, 'school_class_id' => $class->id,
        'academic_year_id' => $year->id, 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);
    foreach ($this->keys as $key) {
        StudentAttendance::create(['schedule_id' => (int) substr($key, 4), 'student_id' => $this->student->id,
            'attendance_date' => today(), 'status' => 'present', 'recorded_by' => $this->teacher->id]);
    }
    $this->complete = ['status' => 'completed', 'material' => 'Persamaan linear', 'notes' => 'Tidak ada catatan', 'reflection' => 'Latihan lanjutan diperlukan'];
});

it('requires one journal per session and LCKH, while drafts do not satisfy checkout', function () {
    expect($this->keys)->toHaveCount(2);
    $this->service->save($this->teacher, today(), $this->keys[0], array_replace($this->complete, ['status' => 'draft']));
    expect($this->service->requirements($this->teacher, today())['missing'])->toHaveCount(2);
    foreach ($this->keys as $key) {
        $this->service->save($this->teacher, today(), $key, $this->complete);
    }
    expect($this->service->completionErrors($this->teacher))->toBe(['LCKH hari ini belum lengkap.']);
    $this->service->save($this->teacher, today(), 'lckh', ['status' => 'completed', 'activities' => 'Mengajar dan menilai tugas']);
    expect($this->service->completionErrors($this->teacher))->toBe([]);
    expect(GtkWorkAudit::where('event', 'journal_saved')->count())->toBe(4);
});

it('retains teaching obligations for an admin whose second role is teacher and permits journals after switching', function () {
    $this->teacher->update(['role' => 'admin', 'secondary_role' => 'teacher']);
    expect($this->service->requirements($this->teacher, today())['sessions'])->toHaveCount(2);
    $this->get(route('gtk.journals.index'))->assertForbidden();
    $this->post(route('role.switch'), ['role' => 'teacher'])->assertRedirect(route('teacher.dashboard'));
    $this->get(route('gtk.journals.index'))->assertOk()->assertSee('Matematika');
    $this->post(route('gtk.journals.store'), $this->complete + ['date' => today()->toDateString(), 'session_key' => $this->keys[0]])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(GtkWorkJournal::where('gtk_id', $this->teacher->gtk_id)->where('kind', 'teaching')->count())->toBe(1);
    $this->actingAs($this->headmaster)->get(route('headmaster.teaching-journals.index'))
        ->assertOk()->assertSee($this->teacher->gtk->full_name);
});

it('deletes owned unapproved journals from the form and history while retaining audit and attendance', function (string $kind, string $status, ?string $approvalStatus) {
    $daily = $kind === 'daily';
    $key = $daily ? 'lckh' : $this->keys[0];
    $prefix = $daily ? 'gtk.lckh' : 'gtk.journals';
    $this->service->save($this->teacher, today(), $key, $daily
        ? ['status' => $status, 'activities' => 'Kegiatan yang dihapus']
        : array_replace($this->complete, ['status' => $status]));
    $journal = GtkWorkJournal::where('session_key', $key)->firstOrFail();
    if ($approvalStatus) {
        GtkDailyApproval::create(['gtk_id' => $this->teacher->gtk_id, 'date' => today(), 'status' => $approvalStatus, 'notes' => 'Catatan pemeriksa']);
    }
    $url = route($prefix.'.destroy', $journal);
    $this->get(route($prefix.'.index'))->assertOk()->assertSee($url, false);
    $this->get(route($prefix.'.history'))->assertOk()->assertSee($url, false);
    $attendanceCount = StudentAttendance::count();
    $this->delete($url, ['return_to' => 'history'])
        ->assertRedirect(route($prefix.'.history', ['month' => '2026-10']))->assertSessionHas('status');

    expect(GtkWorkJournal::find($journal->id))->toBeNull()
        ->and(StudentAttendance::count())->toBe($attendanceCount);
    $audit = GtkWorkAudit::where('event', 'journal_deleted')->sole();
    expect($audit->before['id'])->toBe($journal->id)
        ->and($audit->before['session_key'])->toBe($key)
        ->and($audit->after)->toBe([])
        ->and($audit->actor_id)->toBe($this->teacher->id);
    $requirements = $this->service->requirements($this->teacher, today());
    expect($daily ? $requirements['lckh'] : ! $requirements['missing']->has($key))->toBeFalse();
    if ($approvalStatus) {
        expect(GtkDailyApproval::sole()->status)->toBe($daily ? 'pending' : $approvalStatus)
            ->and(GtkDailyApproval::sole()->notes)->toBe('Catatan pemeriksa');
    }
    $this->get(route($prefix.'.history'))->assertOk()->assertSee($daily ? 'LCKH berhasil dihapus.' : 'Jurnal berhasil dihapus.');
})->with(['daily', 'teaching'])->with(['draft', 'completed'])->with([null, 'pending', 'revision_requested']);

it('hides deletion and rejects direct deletion after daily approval including stale journal models', function (string $kind) {
    $daily = $kind === 'daily';
    $key = $daily ? 'lckh' : $this->keys[0];
    $prefix = $daily ? 'gtk.lckh' : 'gtk.journals';
    $this->service->save($this->teacher, today(), $key, $daily
        ? ['status' => 'completed', 'activities' => 'Kegiatan disetujui'] : $this->complete);
    $journal = GtkWorkJournal::where('session_key', $key)->firstOrFail();
    GtkDailyApproval::create(['gtk_id' => $this->teacher->gtk_id, 'date' => today(), 'status' => 'approved']);
    $url = route($prefix.'.destroy', $journal);
    $this->get(route($prefix.'.index'))->assertOk()->assertDontSee($url, false);
    $this->get(route($prefix.'.history'))->assertOk()->assertDontSee($url, false);
    $this->from(route($prefix.'.history'))->delete($url)->assertSessionHasErrors('journal');
    expect(fn () => $this->service->delete($this->teacher, $journal))->toThrow(ValidationException::class);
    expect(GtkWorkJournal::find($journal->id))->not->toBeNull()
        ->and(GtkDailyApproval::sole()->status)->toBe('approved')
        ->and(GtkWorkAudit::where('event', 'journal_deleted')->count())->toBe(0);
})->with(['daily', 'teaching']);

it('rejects deleting another GTKs journal and mismatched journal endpoints', function (string $kind) {
    $daily = $kind === 'daily';
    $key = $daily ? 'lckh' : $this->keys[0];
    $prefix = $daily ? 'gtk.lckh' : 'gtk.journals';
    $this->service->save($this->teacher, today(), $key, $daily
        ? ['status' => 'draft', 'activities' => 'Milik guru'] : $this->complete);
    $journal = GtkWorkJournal::where('session_key', $key)->firstOrFail();
    $this->actingAs($this->other)->delete(route($prefix.'.destroy', $journal))->assertForbidden();
    expect(fn () => $this->service->delete($this->other, $journal))->toThrow(HttpException::class);
    $this->actingAs($this->teacher)->delete(route($daily ? 'gtk.journals.destroy' : 'gtk.lckh.destroy', $journal))->assertNotFound();
    $unlinked = User::factory()->create(['role' => 'teacher', 'gtk_id' => null]);
    $this->actingAs($unlinked)->delete(route($prefix.'.destroy', $journal))->assertForbidden();
    expect(GtkWorkJournal::find($journal->id))->not->toBeNull()
        ->and(GtkWorkAudit::where('event', 'journal_deleted')->count())->toBe(0);
})->with(['daily', 'teaching']);

it('allows staff to delete their LCKH but denies teaching journal deletion', function () {
    $this->service->save($this->staff, today(), 'lckh', ['status' => 'draft', 'activities' => 'Tugas tendik']);
    $daily = GtkWorkJournal::where('gtk_id', $this->staff->gtk_id)->sole();
    $this->actingAs($this->staff)->delete(route('gtk.lckh.destroy', $daily))
        ->assertRedirect(route('gtk.lckh.index', ['date' => today()->toDateString()]));
    $teaching = GtkWorkJournal::create(['gtk_id' => $this->staff->gtk_id, 'journal_date' => today(),
        'kind' => 'teaching', 'session_key' => 'kbm-99', 'label' => 'Jurnal lama']);
    $this->delete(route('gtk.journals.destroy', $teaching))->assertForbidden();
    expect(fn () => $this->service->delete($this->staff, $teaching))->toThrow(HttpException::class);
    expect(GtkWorkJournal::find($teaching->id))->not->toBeNull();
});

it('requires one attendance record and one journal for a lesson continuing after a break', function () {
    StudentAttendance::query()->delete();
    Schedule::query()->delete();
    $this->seed(KbmTimeSeeder::class);
    $assignment = TeachingAssignment::first();
    $jp4 = LessonPeriod::where('day_of_week', 1)->where('period_number', 4)->first();
    $admin = User::factory()->create(['role' => 'admin']);
    app(ScheduleSessionService::class)->save($admin, [
        'academicYearId' => $assignment->academic_year_id, 'teachingAssignmentId' => $assignment->id,
        'lessonPeriodId' => $jp4->id, 'duration' => 2, 'roomId' => Room::first()->id,
        'dayOfWeek' => 1, 'isActive' => true,
    ]);
    $sessions = app(StudentAttendanceSessionService::class)->forUser($this->teacher, today()->toDateString());
    expect($sessions)->toHaveCount(1)->and($sessions->first()->schedules)->toHaveCount(2);
    $anchor = $sessions->first()->id;
    $key = 'kbm-'.$anchor;
    expect($this->service->sessions($this->teacher->gtk_id, today())->keys()->all())->toBe([$key]);
    expect(fn () => $this->service->save($this->teacher, today(), $key, $this->complete))->toThrow(ValidationException::class);
    StudentAttendance::create(['schedule_id' => $anchor, 'student_id' => $this->student->id,
        'attendance_date' => today(), 'status' => 'present', 'recorded_by' => $this->teacher->id]);
    $this->service->save($this->teacher, today(), $key, $this->complete);
    $requirements = $this->service->requirements($this->teacher, today());
    expect($requirements['sessions'])->toHaveCount(1)->and($requirements['missing'])->toBeEmpty()
        ->and(StudentAttendance::count())->toBe(1)->and(GtkWorkJournal::count())->toBe(1)
        ->and(GtkWorkJournal::first()->schedule_ids)->toBe($sessions->first()->schedules->pluck('id')->all());
    expect(fn () => app(ScheduleSessionService::class)->delete($admin, $sessions->first()->schedules->last()->id))
        ->toThrow(ValidationException::class);
    expect(Schedule::count())->toBe(2);
});

it('rejects blank teaching fields even when a completed status is submitted directly', function () {
    $this->service->save($this->teacher, today(), $this->keys[0], array_replace($this->complete, ['reflection' => '   ']));
})->throws(ValidationException::class);

it('requires only LCKH for staff and teachers with no KBM', function () {
    foreach ([$this->staff, $this->other] as $user) {
        expect($this->service->requirements($user, today())['sessions'])->toBeEmpty();
        $this->service->save($user, today(), 'lckh', ['status' => 'completed', 'activities' => 'Pelayanan administrasi']);
        expect($this->service->completionErrors($user))->toBe([]);
    }
});

it('does not create teaching obligations on a holiday', function () {
    Holiday::create(['date' => today(), 'name' => 'Libur', 'is_active' => true]);
    expect($this->service->requirements($this->teacher, today())['sessions'])->toBeEmpty();
});

it('keeps KBM obligations across the semester boundary and stops at the school year boundary', function () {
    $semester = Semester::create(['academic_year_id' => $this->teacher->gtk->teachingAssignments()->first()->academic_year_id,
        'name' => 'Ganjil', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => false]);
    $this->teacher->gtk->teachingAssignments()->update(['semester_id' => $semester->id]);
    expect($this->service->sessions($this->teacher->gtk_id, Carbon::parse('2027-01-04')))->toHaveCount(2);
    expect($this->service->sessions($this->teacher->gtk_id, Carbon::parse('2027-07-05')))->toBeEmpty();
});

it('denies another teachers session and future journal dates', function () {
    $this->actingAs($this->other)->post(route('gtk.journals.store'), $this->complete + ['date' => today()->toDateString(), 'session_key' => $this->keys[0]])->assertForbidden();
    $this->post(route('gtk.lckh.store'), ['date' => today()->addDay()->toDateString(), 'status' => 'completed', 'activities' => 'Kegiatan'])->assertSessionHasErrors('date');
    expect(GtkWorkJournal::count())->toBe(0);
});

it('blocks checkout until daily LCKH and every required teaching journal are complete', function () {
    $component = Livewire::test('gtk.attendance.index')->call('checkIn', -7.5, 109.5, 5)
        ->assertSee('Lengkapi LCKH dan Jurnal Terlebih Dahulu');
    $component->call('checkOut', -7.5, 109.5, 5)->assertSet('errorMessage', fn ($message) => str_contains($message, 'LCKH hari ini belum lengkap.'));
    expect(GtkAttendance::first()->check_out_at)->toBeNull();
    $this->service->save($this->teacher, today(), 'lckh', ['status' => 'draft', 'activities' => 'Mengajar']);
    $component->call('checkOut', -7.5, 109.5, 5);
    expect(GtkAttendance::first()->check_out_at)->toBeNull();
    $this->service->save($this->teacher, today(), 'lckh', ['status' => 'completed', 'activities' => 'Mengajar']);
    foreach ($this->keys as $key) {
        $this->service->save($this->teacher, today(), $key, array_replace($this->complete, ['status' => 'draft']));
    }
    $component->call('checkOut', -7.5, 109.5, 5)->assertSet('errorMessage', fn ($message) => str_contains($message, 'Jurnal mengajar belum lengkap'));
    expect(GtkAttendance::first()->check_out_at)->toBeNull();
    foreach ($this->keys as $key) {
        $this->service->save($this->teacher, today(), $key, $this->complete);
    }
    $component->call('checkOut', -7.5, 109.5, 5)->assertSet('errorMessage', '');
    expect(GtkAttendance::first()->check_out_at)->not->toBeNull();
    expect(GtkDailyApproval::where('status', 'approved')->exists())->toBeFalse();
});

it('requires only completed daily LCKH for GTK with no teaching sessions', function (string $role) {
    $user = $this->$role;
    $this->actingAs($user);
    $component = Livewire::test('gtk.attendance.index')->call('checkIn', -7.5, 109.5, 5);
    $component->call('checkOut', -7.5, 109.5, 5)->assertSet('errorMessage', fn ($message) => str_contains($message, 'LCKH hari ini belum lengkap.'));
    expect(GtkAttendance::where('gtk_id', $user->gtk_id)->first()->check_out_at)->toBeNull();
    $this->service->save($user, today(), 'lckh', ['status' => 'completed', 'activities' => 'Tugas harian']);
    $component->call('checkOut', -7.5, 109.5, 5)->assertSet('errorMessage', '');
    expect(GtkAttendance::where('gtk_id', $user->gtk_id)->first()->check_out_at)->not->toBeNull();
})->with(['staff', 'headmaster', 'other']);

it('allows journal drafts after checkout because attendance does not approve the journal', function () {
    GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => today(), 'check_in_at' => now(), 'check_out_at' => now()]);
    $this->service->save($this->teacher, today(), $this->keys[0], $this->complete);
    $this->service->save($this->teacher, today(), $this->keys[0], ['status' => 'draft']);
    expect(GtkWorkJournal::first()->isComplete())->toBeFalse();
});

it('supports daily approval, correction and re-approval with immutable revision records', function () {
    $this->service->save($this->teacher, today(), 'lckh', ['status' => 'completed', 'activities' => 'Kegiatan awal']);
    GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => today(), 'check_in_at' => now(), 'check_out_at' => now()]);
    $this->actingAs($this->headmaster)->get(route('headmaster.work-reviews.index', ['month' => '2026-10']))->assertOk()->assertSee('GTK teacher');
    $this->get(route('headmaster.work-reviews.show', [$this->teacher->gtk_id, 'month' => '2026-10']))->assertOk()->assertSee('Approve Terpilih');
    $this->post(route('headmaster.work-reviews.decide', $this->teacher->gtk_id), ['date' => today()->toDateString(), 'status' => 'revision_requested', 'notes' => 'Perjelas hasil kegiatan tanggal 26'])->assertRedirect();
    expect(GtkDailyApproval::first()->status)->toBe('revision_requested');
    $this->actingAs($this->teacher)->get(route('gtk.lckh.index'))->assertOk()->assertSee('Perjelas hasil kegiatan tanggal 26');
    $this->service->save($this->teacher, today(), 'lckh', ['status' => 'completed', 'activities' => 'Hasil kegiatan diperjelas']);
    expect(GtkDailyApproval::first()->status)->toBe('pending');
    app(GtkDailyApprovalService::class)->decide($this->headmaster, $this->teacher->gtk_id, today(), ['status' => 'approved', 'notes' => 'Sudah sesuai']);
    expect(GtkDailyApproval::first()->status)->toBe('approved');
    expect(GtkWorkAudit::where('event', 'daily_approval')->count())->toBe(2);
    expect(GtkWorkAudit::where('event', 'journal_saved')->latest('id')->first()->before['activities'])->toBe('Kegiatan awal');
});

it('restricts daily approvals and requires a reason for corrections', function () {
    $this->get(route('headmaster.work-reviews.index'))->assertForbidden();
    $this->post(route('headmaster.work-reviews.decide', $this->teacher->gtk_id), ['date' => today()->toDateString(), 'status' => 'approved'])->assertForbidden();
    $this->actingAs($this->headmaster)->post(route('headmaster.work-reviews.decide', $this->teacher->gtk_id), ['date' => today()->toDateString(), 'status' => 'revision_requested'])->assertSessionHasErrors('notes');
});

it('removes cancelled KBM and assigns substituted KBM to its actual teacher', function () {
    $this->service->exception($this->headmaster, $this->teacher, today(), $this->keys[0], null, 'Kegiatan sekolah');
    $this->service->exception($this->headmaster, $this->teacher, today(), $this->keys[1], $this->other->gtk_id, 'Guru berhalangan');
    $this->actingAs($this->other)->get(route('teacher.student-attendances.index', ['date' => today()->toDateString(), 'schedule' => (int) substr($this->keys[1], 4)]))->assertOk()->assertSee('Siswa Jurnal');
    expect($this->service->sessions($this->teacher->gtk_id, today()))->toBeEmpty();
    expect($this->service->sessions($this->other->gtk_id, today()))->toHaveCount(1);
    $this->service->save($this->other, today(), $this->keys[1], $this->complete);
    expect(GtkKbmException::count())->toBe(2);
    expect($this->service->requirements($this->other, today())['missing'])->toBeEmpty();
});

it('removes checkout exception actions and ignores historical exceptions during checkout', function () {
    DB::table('gtk_checkout_exceptions')->insert(['gtk_id' => $this->teacher->gtk_id, 'date' => today()->toDateString(),
        'reason' => 'Pengecualian lama', 'created_by' => $this->headmaster->id]);
    expect($this->service->completionErrors($this->teacher))->toContain('LCKH hari ini belum lengkap.');
    $this->actingAs($this->headmaster)->post('/headmaster/journals/'.$this->teacher->gtk_id.'/checkout-exceptions',
        ['date' => today()->toDateString(), 'reason' => 'Gangguan sistem'])->assertNotFound();
    GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => today(), 'check_in_at' => now()]);
    $this->get(route('headmaster.work-reviews.show', $this->teacher->gtk_id))->assertOk()->assertDontSee('Pengecualian checkout');
    $this->actingAs($this->teacher)->get(route('gtk.attendance.index'))->assertOk()->assertDontSee('Pengecualian lama');
    Livewire::test('gtk.attendance.index')->call('checkOut', -7.5, 109.5, 5)
        ->assertSet('errorMessage', fn ($message) => str_contains($message, 'LCKH hari ini belum lengkap.'));
    expect(GtkAttendance::first()->check_out_at)->toBeNull();
});

it('records forgotten teacher checkout as PSW3 independently of LCKH and teaching journals', function () {
    $this->travelTo(Carbon::parse('2026-10-27 14:00:00'));
    $date = Carbon::parse('2026-10-26');
    $attendance = GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => $date,
        'check_in_at' => '2026-10-26 07:00']);
    $component = Livewire::test('gtk.attendance.history')->call('openCorrection', '2026-10-26')
        ->set('reason', 'Lupa presensi pulang setelah mengajar')
        ->call('saveForgotten')->assertHasNoErrors()->assertSet('correctionDate', null)->assertSee('PSW3');
    expect($attendance->fresh()->check_out_at)->toBeNull()->and($attendance->fresh()->forgotten_checkout_at)->not->toBeNull()
        ->and(GtkAttendance::whereDate('attendance_date', today())->exists())->toBeFalse();
});

it('keeps LCKH approval unchanged when attendance changes', function () {
    GtkDailyApproval::create(['gtk_id' => $this->teacher->gtk_id, 'date' => today(), 'status' => 'approved']);
    GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => today(), 'check_in_at' => now()]);
    expect(GtkDailyApproval::first()->status)->toBe('approved');
});

it('includes the last day of the month in monthly recaps and journal details', function () {
    $this->travelTo(Carbon::parse('2026-10-31 14:00:00'));
    $this->service->save($this->staff, today(), 'lckh', ['status' => 'completed', 'activities' => 'Kegiatan akhir bulan']);
    GtkAttendance::create(['gtk_id' => $this->staff->gtk_id, 'attendance_date' => today(), 'check_in_at' => now()]);
    $this->actingAs($this->headmaster)->get(route('headmaster.work-reviews.index', ['month' => '2026-10']))
        ->assertOk()->assertViewHas('lckhs', fn ($groups) => $groups->get($this->staff->gtk_id)->count() === 1);
    $this->get(route('headmaster.work-reviews.show', [$this->staff->gtk_id, 'month' => '2026-10']))
        ->assertOk()->assertSee('Kegiatan akhir bulan')->assertSee('31/10/2026');
});

it('retains LCKH detail for a GTK without a login account', function () {
    $gtk = Gtk::create(['full_name' => 'GTK tanpa akun']);
    GtkAttendance::create(['gtk_id' => $gtk->id, 'attendance_date' => today(), 'status' => 'sick']);
    GtkWorkJournal::create(['gtk_id' => $gtk->id, 'journal_date' => today(), 'kind' => 'daily', 'session_key' => 'lckh',
        'label' => 'LCKH', 'activities' => 'Kegiatan GTK tanpa akun', 'status' => 'completed']);
    $this->actingAs($this->headmaster)->get(route('headmaster.work-reviews.show', [$gtk, 'month' => '2026-10']))
        ->assertOk()->assertSee('26/10/2026')->assertSee('Kegiatan GTK tanpa akun')->assertDontSee('Sakit');
});

it('separates teaching journals from LCKH and links journals to learning sessions', function () {
    $this->get(route('gtk.journals.index'))->assertOk()->assertSee('Uraian materi')->assertDontSee('name="activities"', false);
    $this->get(route('gtk.lckh.index'))->assertOk()->assertSee('name="activities"', false)->assertDontSee('name="material"', false);
    $this->get(route('teacher.schedules.index'))->assertOk()->assertSee('Jurnal Sesi Pembelajaran')
        ->assertSee('#'.$this->keys[0], false);
    $this->post(route('gtk.lckh.store'), ['date' => today()->toDateString(), 'status' => 'completed', 'activities' => 'Administrasi pembelajaran'])
        ->assertRedirect(route('gtk.lckh.index', ['date' => today()->toDateString()]));
    $this->post(route('gtk.journals.store'), $this->complete + ['date' => today()->toDateString(), 'session_key' => 'lckh'])->assertSessionHasErrors('session_key');
});

it('shows only LCKH obligations to staff and denies teaching journal access', function () {
    // Even stale timetable assignments cannot turn teaching journals into staff obligations.
    TeachingAssignment::query()->update(['gtk_id' => $this->staff->gtk_id]);
    $this->actingAs($this->staff)->get(route('gtk.attendance.index'))->assertOk()->assertSee('Isi LCKH')->assertDontSee('Jurnal mengajar:', false)->assertDontSee('Isi Jurnal Mengajar');
    $this->get(route('gtk.lckh.index'))->assertOk()->assertDontSee('Jurnal Mengajar');
    $this->get(route('gtk.journals.index'))->assertForbidden();
    $this->post(route('gtk.journals.store'), $this->complete + ['date' => today()->toDateString(), 'session_key' => $this->keys[0]])->assertForbidden();
    expect($this->service->completionErrors($this->staff))->toBe(['LCKH hari ini belum lengkap.']);
    expect(fn () => $this->service->save($this->staff, today(), $this->keys[0], $this->complete))->toThrow(HttpException::class);
});

it('locks journal entry until every student has attendance in the matching session and date', function () {
    StudentAttendance::query()->delete();
    $this->get(route('gtk.journals.index'))->assertOk()->assertSee('Jurnal terkunci')->assertSee('disabled', false);
    foreach (['draft', 'completed'] as $status) {
        expect(fn () => $this->service->save($this->teacher, today(), $this->keys[0], array_replace($this->complete, ['status' => $status])))
            ->toThrow(ValidationException::class);
    }
    StudentAttendance::create(['schedule_id' => (int) substr($this->keys[0], 4), 'student_id' => $this->student->id,
        'attendance_date' => today()->subWeek(), 'status' => 'present', 'recorded_by' => $this->teacher->id]);
    expect(fn () => $this->service->save($this->teacher, today(), $this->keys[0], $this->complete))->toThrow(ValidationException::class);
    StudentAttendance::create(['schedule_id' => (int) substr($this->keys[1], 4), 'student_id' => $this->student->id,
        'attendance_date' => today(), 'status' => 'present', 'recorded_by' => $this->teacher->id]);
    expect(fn () => $this->service->save($this->teacher, today(), $this->keys[0], $this->complete))->toThrow(ValidationException::class);
    $component = Livewire::test('admin.student-attendances.index')->set('scheduleId', (int) substr($this->keys[0], 4))
        ->set('attendance.'.$this->student->id, 'absent')->call('saveAttendance')->assertHasNoErrors();
    $this->service->save($this->teacher, today(), $this->keys[0], $this->complete);
    expect(GtkWorkJournal::where('kind', 'teaching')->count())->toBe(1);
});

it('does not accept partial attendance or let attendance prerequisites affect LCKH', function () {
    $history = StudentClassHistory::first();
    $second = Student::create(['student_code' => 'JRN-2', 'full_name' => 'Siswa Kedua']);
    StudentClassHistory::create(['student_id' => $second->id, 'school_class_id' => $history->school_class_id,
        'academic_year_id' => $history->academic_year_id]);
    expect(fn () => $this->service->save($this->teacher, today(), $this->keys[0], $this->complete))->toThrow(ValidationException::class);
    $this->service->save($this->teacher, today(), 'lckh', ['status' => 'completed', 'activities' => 'Kegiatan harian']);
    expect(GtkWorkJournal::where('kind', 'daily')->count())->toBe(1);
});

it('opens the matching student attendance session from journal links', function () {
    $this->get(route('teacher.student-attendances.index', ['date' => today()->toDateString(), 'schedule' => (int) substr($this->keys[0], 4)]))
        ->assertOk()->assertSee('Siswa Jurnal');
    $this->actingAs($this->other)->get(route('teacher.student-attendances.index', ['date' => today()->toDateString(), 'schedule' => (int) substr($this->keys[0], 4)]))->assertForbidden();
});

it('approves only the selected complete dates and leaves other dates and GTK unchanged', function () {
    $service = app(GtkDailyApprovalService::class);
    $this->service->save($this->teacher, today(), 'lckh', ['status' => 'completed', 'activities' => 'Kegiatan lengkap']);
    foreach ([[$this->teacher, '2026-10-23'], [$this->teacher, '2026-10-24'], [$this->other, '2026-10-26']] as [$user, $date]) {
        GtkWorkJournal::create(['gtk_id' => $user->gtk_id, 'journal_date' => $date, 'kind' => 'daily', 'session_key' => 'lckh',
            'label' => 'LCKH', 'activities' => 'Kegiatan lengkap', 'status' => 'completed']);
    }
    GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => today(), 'check_in_at' => now(), 'check_out_at' => now()]);
    GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => '2026-10-23', 'status' => 'sick']);
    GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => '2026-10-24', 'status' => 'sick']);
    GtkAttendance::create(['gtk_id' => $this->staff->gtk_id, 'attendance_date' => today(), 'check_in_at' => now()]);
    GtkAttendance::create(['gtk_id' => $this->other->gtk_id, 'attendance_date' => today(), 'status' => 'sick']);
    $service->decide($this->headmaster, $this->other->gtk_id, today(), ['status' => 'revision_requested', 'notes' => 'Lengkapi catatan sakit']);
    $data = ['month' => '2026-10', 'dates' => ['2026-10-23', today()->toDateString()]];
    $this->actingAs($this->headmaster)->post(route('headmaster.work-reviews.approve-selected', $this->teacher->gtk_id), $data)
        ->assertRedirect(route('headmaster.work-reviews.show', [$this->teacher->gtk_id, 'month' => '2026-10']));
    expect(GtkDailyApproval::where('gtk_id', $this->teacher->gtk_id)->where('status', 'approved')->orderBy('date')->get()
        ->map(fn ($approval) => $approval->date->toDateString())->all())->toBe(['2026-10-23', '2026-10-26']);
    expect(GtkDailyApproval::where('gtk_id', $this->teacher->gtk_id)->whereDate('date', '2026-10-24')->exists())->toBeFalse();
    expect(GtkDailyApproval::where('gtk_id', $this->staff->gtk_id)->exists())->toBeFalse();
    expect(GtkDailyApproval::where('gtk_id', $this->other->gtk_id)->first()->status)->toBe('revision_requested');
    $auditCount = GtkWorkAudit::where('event', 'daily_approval')->count();
    $service->approveSelected($this->headmaster, $this->teacher->gtk_id, today()->startOfMonth(), $data);
    expect(GtkWorkAudit::where('event', 'daily_approval')->count())->toBe($auditCount);
    expect(fn () => $service->decide($this->headmaster, $this->staff->gtk_id, today(), ['status' => 'approved']))->toThrow(ValidationException::class);
});

it('isolates approval dates and does not invalidate daily approval when teaching journals change', function () {
    GtkDailyApproval::create(['gtk_id' => $this->teacher->gtk_id, 'date' => today(), 'status' => 'approved']);
    GtkDailyApproval::create(['gtk_id' => $this->teacher->gtk_id, 'date' => today()->subDay(), 'status' => 'approved']);
    $this->service->save($this->teacher, today(), $this->keys[0], $this->complete);
    expect(GtkDailyApproval::where('status', 'approved')->count())->toBe(2);
    $this->service->save($this->teacher, today(), 'lckh', ['status' => 'completed', 'activities' => 'Kegiatan diperbaiki']);
    expect(GtkDailyApproval::whereDate('date', today())->first()->status)->toBe('pending');
    expect(GtkDailyApproval::whereDate('date', today()->subDay())->first()->status)->toBe('approved');
});

it('separates daily review and teaching journal modules and preserves session period counts', function () {
    $this->service->save($this->teacher, today(), $this->keys[0], $this->complete);
    $this->actingAs($this->headmaster)->get(route('headmaster.work-reviews.show', [$this->teacher->gtk_id, 'month' => '2026-10']))
        ->assertOk()->assertDontSee('Persamaan linear')->assertDontSee('Download Presensi')->assertSee('Download LCKH');
    $this->get(route('headmaster.teaching-journals.show', [$this->teacher->gtk_id, 'month' => '2026-10']))
        ->assertOk()->assertSee('Persamaan linear')->assertSee('VII A')->assertSee('Matematika')->assertSee('Download Jurnal Mengajar')->assertDontSee('Approve Semua');
    $rows = app(GtkReportService::class)->teachingRows($this->teacher->gtk_id, today()->startOfMonth());
    expect($rows[0]['periods'])->toBe(2)->and($rows[0]['class'])->toBe('VII A');
});

it('downloads three distinct PDFs with the official shared letterhead and no cross-module content', function () {
    $this->service->save($this->teacher, today(), $this->keys[0], $this->complete);
    $this->service->save($this->teacher, today(), 'lckh', ['status' => 'completed', 'activities' => 'Kegiatan harian unik']);
    GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => today(), 'check_in_at' => now(), 'check_out_at' => now()]);
    $this->actingAs($this->headmaster);
    foreach (['attendance', 'lckh', 'teaching'] as $kind) {
        $response = $this->get(route('headmaster.gtk-reports.download', [$this->teacher->gtk_id, $kind, 'month' => '2026-10']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($response->getContent())->toStartWith('%PDF-');
        $html = view('pdf.gtk.report', app(GtkReportService::class)->data($this->teacher->gtk, today()->startOfMonth(), $kind))->render();
        expect($html)->toContain('LEMBAGA PENDIDIKAN')->toContain('kop-logo');
        if ($kind === 'teaching') {
            expect($html)->toContain('Persamaan linear')->not->toContain('Kegiatan harian unik');
        } elseif ($kind === 'lckh') {
            expect($html)->toContain('Kegiatan harian unik')->not->toContain('Persamaan linear');
        } else {
            expect($html)->not->toContain('Kegiatan harian unik')->not->toContain('Persamaan linear');
        }
    }
    $this->actingAs($this->teacher)->get(route('headmaster.gtk-reports.download', [$this->teacher->gtk_id, 'attendance']))->assertForbidden();
});

it('limits selected approvals to that GTK and rejects future dates and periods', function () {
    foreach ([$this->teacher, $this->staff] as $user) {
        GtkAttendance::create(['gtk_id' => $user->gtk_id, 'attendance_date' => today(), 'status' => 'sick']);
        GtkWorkJournal::create(['gtk_id' => $user->gtk_id, 'journal_date' => today(), 'kind' => 'daily', 'session_key' => 'lckh',
            'label' => 'LCKH', 'activities' => 'Kegiatan lengkap', 'status' => 'completed']);
    }
    $this->actingAs($this->headmaster)->post(route('headmaster.work-reviews.approve-selected', $this->teacher->gtk_id),
        ['month' => '2026-10', 'dates' => [today()->toDateString()]])->assertRedirect();
    expect(GtkDailyApproval::where('gtk_id', $this->teacher->gtk_id)->where('status', 'approved')->count())->toBe(1);
    expect(GtkDailyApproval::where('gtk_id', $this->staff->gtk_id)->exists())->toBeFalse();
    $this->post(route('headmaster.work-reviews.decide', $this->teacher->gtk_id), ['date' => today()->addDay()->toDateString(), 'status' => 'approved'])->assertSessionHasErrors('date');
    $this->post(route('headmaster.work-reviews.approve-selected', $this->teacher->gtk_id),
        ['month' => '2026-11', 'dates' => [today()->toDateString()]])->assertSessionHasErrors('month');
});

it('shows date selection instead of approve-all buttons and hides the audit history', function () {
    foreach (['2026-10-23', '2026-10-24', '2026-10-26'] as $date) {
        GtkWorkJournal::create(['gtk_id' => $this->teacher->gtk_id, 'journal_date' => $date, 'kind' => 'daily', 'session_key' => 'lckh',
            'label' => 'LCKH', 'activities' => 'Kegiatan', 'status' => $date === '2026-10-24' ? 'draft' : 'completed']);
    }
    GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => today(), 'status' => 'sick']);
    GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => '2026-10-23', 'status' => 'sick']);
    GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => '2026-10-24', 'status' => 'present', 'check_in_at' => '2026-10-24 07:00']);
    $service = app(GtkDailyApprovalService::class);
    $service->decide($this->headmaster, $this->teacher->gtk_id, Carbon::parse('2026-10-23'), ['status' => 'revision_requested', 'notes' => 'Periksa bukti']);
    $this->service->audit($this->headmaster, $this->teacher->gtk_id, today(), 'attendance_saved', null, ['notes' => 'Isi audit tersembunyi']);
    $auditCount = GtkWorkAudit::count();
    $this->actingAs($this->headmaster)->get(route('headmaster.work-reviews.index'))
        ->assertOk()->assertDontSee('Approve Semua')->assertSee('centang tanggal');
    $this->get(route('headmaster.work-reviews.show', [$this->teacher->gtk_id, 'month' => '2026-10']))
        ->assertOk()->assertSee('Approve Terpilih')->assertSee('name="dates[]"', false)->assertSee('form="approve-selected"', false)
        ->assertViewHas('selectableDates', [today()->toDateString()])
        ->assertDontSee('Approve Semua')->assertDontSee('Riwayat approval')->assertDontSee('Isi audit tersembunyi');
    expect(GtkWorkAudit::count())->toBe($auditCount);
});

it('requires a nonempty valid selection within the displayed month', function (array $input, string $field) {
    $this->actingAs($this->headmaster)->post(route('headmaster.work-reviews.approve-selected', $this->teacher->gtk_id), $input)
        ->assertSessionHasErrors($field);
    expect(GtkDailyApproval::count())->toBe(0)->and(GtkWorkAudit::where('event', 'daily_approval')->count())->toBe(0);
})->with([
    [['month' => '2026-10'], 'dates'],
    [['month' => '2026-10', 'dates' => []], 'dates'],
    [['month' => '2026-10', 'dates' => '2026-10-26'], 'dates'],
    [['month' => '2026-10', 'dates' => ['invalid']], 'dates.0'],
    [['month' => '2026-10', 'dates' => ['2026-10-26', '2026-10-26']], 'dates.0'],
    [['month' => '2026-10', 'dates' => ['2026-09-30']], 'dates.0'],
    [['month' => '2026-10', 'dates' => ['2026-10-27']], 'dates.0'],
    [['month' => '2026-10', 'dates' => ['2026-11-01']], 'dates.0'],
]);

it('rolls back the whole selection when a chosen date is incomplete or still needs correction', function (bool $correction) {
    foreach (['2026-10-23', '2026-10-24'] as $date) {
        GtkWorkJournal::create(['gtk_id' => $this->teacher->gtk_id, 'journal_date' => $date, 'kind' => 'daily', 'session_key' => 'lckh',
            'label' => 'LCKH', 'activities' => 'Kegiatan', 'status' => $date === '2026-10-24' && ! $correction ? 'draft' : 'completed']);
    }
    GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => '2026-10-23', 'status' => 'sick']);
    GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => '2026-10-24', 'status' => $correction ? 'sick' : 'present']);
    if ($correction) {
        app(GtkDailyApprovalService::class)->decide($this->headmaster, $this->teacher->gtk_id, Carbon::parse('2026-10-24'),
            ['status' => 'revision_requested', 'notes' => 'Lengkapi bukti']);
    }
    $auditCount = GtkWorkAudit::where('event', 'daily_approval')->count();
    $this->actingAs($this->headmaster)->post(route('headmaster.work-reviews.approve-selected', $this->teacher->gtk_id),
        ['month' => '2026-10', 'dates' => ['2026-10-23', '2026-10-24']])->assertSessionHasErrors('dates');
    expect(GtkDailyApproval::where('status', 'approved')->count())->toBe(0)
        ->and(GtkDailyApproval::whereDate('date', '2026-10-23')->exists())->toBeFalse()
        ->and(GtkWorkAudit::where('event', 'daily_approval')->count())->toBe($auditCount);
})->with([false, true]);

it('restricts selected approval to headmasters', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->post(route('headmaster.work-reviews.approve-selected', $this->teacher->gtk_id),
        ['month' => '2026-10', 'dates' => [today()->toDateString()]])->assertForbidden();
    expect(fn () => app(GtkDailyApprovalService::class)->approveSelected($user, $this->teacher->gtk_id, today(),
        ['dates' => [today()->toDateString()]]))->toThrow(HttpException::class);
    expect(GtkDailyApproval::count())->toBe(0);
})->with(['admin', 'teacher', 'staff']);

it('removes the old approve-all endpoints without modifying approvals', function () {
    $this->actingAs($this->headmaster)->post('/headmaster/work-reviews/approve-all', ['month' => '2026-10'])->assertClientError();
    $this->post('/headmaster/work-reviews/'.$this->teacher->gtk_id.'/approve-all', ['month' => '2026-10'])->assertNotFound();
    expect(GtkDailyApproval::count())->toBe(0);
});

it('preserves long report content across continuation rows without duplicating period totals', function () {
    $long = str_repeat('Materi panjang untuk pengujian rekap. ', 300);
    $this->service->save($this->teacher, today(), $this->keys[0], array_replace($this->complete, ['material' => $long]));
    $data = app(GtkReportService::class)->data($this->teacher->gtk, today()->startOfMonth(), 'teaching');
    expect($data['printRows']->count())->toBeGreaterThan(1);
    expect($data['printRows']->pluck('material')->implode(''))->toBe($long);
    expect($data['rows']->sum('periods'))->toBe(2);
});

it('shows only the signed-in GTKs LCKH history and daily correction notes', function () {
    $this->service->save($this->teacher, today(), 'lckh', ['status' => 'completed', 'activities' => 'Kegiatan pribadi guru']);
    $this->service->save($this->other, today(), 'lckh', ['status' => 'completed', 'activities' => 'Kegiatan rahasia guru lain']);
    $this->service->save($this->teacher, today(), $this->keys[0], $this->complete);
    GtkDailyApproval::create(['gtk_id' => $this->teacher->gtk_id, 'date' => today(), 'status' => 'revision_requested', 'notes' => 'Perjelas hasil kegiatan saya']);
    GtkDailyApproval::create(['gtk_id' => $this->other->gtk_id, 'date' => today(), 'status' => 'revision_requested', 'notes' => 'Catatan rahasia orang lain']);
    $this->get(route('gtk.lckh.history', ['month' => '2026-10', 'gtk_id' => $this->other->gtk_id]))
        ->assertOk()->assertSee('Kegiatan pribadi guru')->assertSee('Perjelas hasil kegiatan saya')->assertSee('Perbaiki LCKH')
        ->assertDontSee('Kegiatan rahasia guru lain')->assertDontSee('Catatan rahasia orang lain')->assertDontSee('Persamaan linear');
    $this->get(route('gtk.lckh.history', ['month' => '2026-10', 'status' => 'approved']))->assertOk()->assertDontSee('Kegiatan pribadi guru');
    $this->get(route('gtk.lckh.history', ['month' => '2026-10', 'status' => 'revision_requested']))
        ->assertOk()->assertViewHas('days', fn ($days) => $days->count() === 1)->assertSee('Kegiatan pribadi guru');
    $this->get(route('gtk.lckh.index', ['date' => today()->toDateString()]))->assertOk()->assertSee('Riwayat LCKH')->assertSee('Perjelas hasil kegiatan saya');
});

it('shows only the teachers own session journals and filters by period and completion status', function () {
    $this->service->save($this->teacher, today(), $this->keys[0], $this->complete);
    $this->service->save($this->teacher, today(), $this->keys[1], array_replace($this->complete, ['status' => 'draft', 'material' => 'Materi draf saya']));
    GtkWorkJournal::create(['gtk_id' => $this->other->gtk_id, 'journal_date' => today(), 'session_key' => 'kbm-99',
        'kind' => 'teaching', 'label' => 'Pelajaran lain / Kelas rahasia / JP 1', 'schedule_ids' => [99], 'status' => 'draft', 'material' => 'Materi rahasia guru lain']);
    GtkWorkJournal::create(['gtk_id' => $this->teacher->gtk_id, 'journal_date' => '2026-09-26', 'session_key' => 'kbm-98',
        'kind' => 'teaching', 'label' => 'Matematika / VII A / JP 1', 'schedule_ids' => [98], 'status' => 'draft', 'material' => 'Materi bulan sebelumnya']);
    $this->get(route('gtk.journals.history', ['month' => '2026-10', 'gtk_id' => $this->other->gtk_id]))
        ->assertOk()->assertSee('Persamaan linear')->assertSee('VII A')->assertSee('Matematika')->assertSee('Refleksi singkat')
        ->assertSee('#'.$this->keys[0], false)->assertDontSee('Materi rahasia guru lain')->assertDontSee('Materi bulan sebelumnya')
        ->assertViewHas('summary', fn ($summary) => $summary === ['sessions' => 2, 'completed' => 1, 'periods' => 3]);
    $this->get(route('gtk.journals.history', ['month' => '2026-10', 'status' => 'completed']))
        ->assertOk()->assertSee('Persamaan linear')->assertDontSee('Materi draf saya');
    $this->get(route('gtk.journals.history', ['month' => '2026-09']))->assertOk()->assertSee('Materi bulan sebelumnya')->assertDontSee('Materi draf saya');
});

it('allows staff LCKH history but forbids teaching history and handles unlinked accounts and invalid filters', function () {
    $this->service->save($this->staff, today(), 'lckh', ['status' => 'draft', 'activities' => 'Kegiatan tendik saya']);
    $this->actingAs($this->staff)->get(route('gtk.lckh.history', ['month' => '2026-10', 'status' => 'draft']))
        ->assertOk()->assertSee('Kegiatan tendik saya')->assertDontSee('Riwayat Jurnal Mengajar');
    $this->get(route('gtk.journals.history'))->assertForbidden();
    $this->get(route('gtk.lckh.history', ['month' => '2026-11']))->assertSessionHasErrors('month');
    $this->get(route('gtk.lckh.history', ['month' => '2026-10', 'status' => 'invalid']))->assertSessionHasErrors('status');
    $unlinked = User::factory()->create(['role' => 'teacher', 'gtk_id' => null]);
    $this->actingAs($unlinked)->get(route('gtk.lckh.history'))->assertForbidden();
    $this->get(route('gtk.journals.history'))->assertForbidden();
});

it('downloads the signed-in GTK report for the selected month regardless of supplied GTK identifiers', function (string $role, string $kind) {
    $user = $role === 'admin'
        ? User::factory()->create(['role' => 'admin', 'gtk_id' => $this->other->gtk_id])
        : $this->$role;
    $this->mock(GtkReportService::class, function ($mock) use ($user, $kind) {
        $mock->shouldReceive('pdf')->once()
            ->withArgs(fn (Gtk $gtk, Carbon $month, string $reportKind) => $gtk->id === $user->gtk_id
                && $month->toDateString() === '2026-09-01' && $reportKind === $kind)
            ->andReturn('%PDF-test');
    });
    $name = ['attendance' => 'presensi', 'lckh' => 'lckh', 'teaching' => 'jurnal-mengajar'][$kind];
    $response = $this->actingAs($user)->get(route('gtk.reports.download', [
        'kind' => $kind, 'month' => '2026-09', 'gtk_id' => $this->teacher->gtk_id, 'gtk' => $this->teacher->gtk_id,
    ]))->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$name.'-gtk-'.$user->gtk_id.'-2026-09.pdf"');
    expect($response->getContent())->toBe('%PDF-test');
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
})->with([
    ['teacher', 'attendance'], ['teacher', 'lckh'], ['teacher', 'teaching'],
    ['staff', 'attendance'], ['staff', 'lckh'],
    ['headmaster', 'attendance'], ['headmaster', 'lckh'], ['headmaster', 'teaching'],
    ['admin', 'attendance'], ['admin', 'lckh'],
]);

it('provides monthly PDF downloads from each GTK history page', function () {
    foreach (['attendance' => 'Download Presensi', 'lckh' => 'Download LCKH', 'teaching' => 'Download Jurnal Mengajar'] as $kind => $label) {
        $history = ['attendance' => 'gtk.attendance.history', 'lckh' => 'gtk.lckh.history', 'teaching' => 'gtk.journals.history'][$kind];
        $this->get(route($history))->assertOk()->assertSee($label)
            ->assertSee(route('gtk.reports.download', ['kind' => $kind, 'month' => '2026-10']), false);
        $response = $this->get(route('gtk.reports.download', ['kind' => $kind, 'month' => '2026-10']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($response->getContent())->toStartWith('%PDF-');
    }
    Livewire::test('gtk.attendance.history')->set('month', '2026-09')
        ->assertSee(route('gtk.reports.download', ['kind' => 'attendance', 'month' => '2026-09']), false);
    $this->actingAs($this->staff)->get(route('gtk.lckh.history', ['month' => '2026-09']))
        ->assertOk()->assertSee(route('gtk.reports.download', ['kind' => 'lckh', 'month' => '2026-09']), false);
    $this->actingAs($this->teacher)->get(route('gtk.journals.history', ['month' => '2026-09']))
        ->assertOk()->assertSee(route('gtk.reports.download', ['kind' => 'teaching', 'month' => '2026-09']), false);
});

it('enforces GTK download permissions and rejects invalid report periods and kinds', function () {
    $this->mock(GtkReportService::class, fn ($mock) => $mock->shouldNotReceive('pdf'));
    $this->actingAs($this->staff)->get(route('gtk.reports.download', ['kind' => 'teaching']))->assertForbidden();
    $admin = User::factory()->create(['role' => 'admin', 'gtk_id' => $this->staff->gtk_id]);
    $this->actingAs($admin)->get(route('gtk.reports.download', ['kind' => 'teaching']))->assertForbidden();
    $unlinked = User::factory()->create(['role' => 'teacher', 'gtk_id' => null]);
    foreach (['attendance', 'lckh', 'teaching'] as $kind) {
        $this->actingAs($unlinked)->get(route('gtk.reports.download', ['kind' => $kind]))->assertForbidden();
    }
    $this->actingAs($this->teacher);
    foreach (['2026-11', 'invalid'] as $month) {
        $this->get(route('gtk.reports.download', ['kind' => 'attendance', 'month' => $month]))->assertSessionHasErrors('month');
    }
    $this->get(route('gtk.reports.download', ['kind' => 'invalid']))->assertNotFound();
    auth()->logout();
    $this->get(route('gtk.reports.download', ['kind' => 'attendance']))->assertRedirect(route('login'));
});
