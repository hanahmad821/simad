<?php

use App\Models\Gtk;
use App\Models\GtkAbsenceRequest;
use App\Models\GtkAttendance;
use App\Models\GtkDailyApproval;
use App\Models\GtkWorkAudit;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\GtkAbsenceService;
use App\Services\GtkReportService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Absence tests require an isolated in-memory database.');
    }
    foreach ([
        '0001_01_01_000000_create_users_table.php',
        '2025_08_14_170933_add_two_factor_columns_to_users_table.php',
        '2026_08_26_064935_create_gtks_table.php',
        '2026_09_08_042246_add_role_and_gtk_id_to_users_table.php', '2026_11_05_000001_add_secondary_role_to_users.php',
        '2026_08_26_081240_create_gtk_attendances_table.php',
        '2026_11_02_000006_add_gtk_attendance_time_references.php',
        '2026_08_26_081832_create_school_settings_table.php',
        '2026_09_10_081647_create_holidays_table.php',
        '2026_10_27_000003_create_gtk_work_journals.php',
        '2026_10_27_000004_create_gtk_daily_approvals.php',
        '2026_11_02_000001_create_gtk_absence_requests.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-11-02 10:00:00'));
    Storage::fake('local');
    foreach (['teacher', 'staff', 'headmaster', 'other', 'admin'] as $role) {
        $gtk = Gtk::create(['full_name' => 'GTK '.$role, 'is_active' => true]);
        $this->$role = User::factory()->create(['role' => $role === 'other' ? 'teacher' : $role, 'gtk_id' => $gtk->id]);
    }
    $this->data = ['type' => 'DL', 'description' => 'Mengikuti rapat koordinasi', 'start_date' => '2026-11-01', 'end_date' => '2026-11-02'];
    $this->service = app(GtkAbsenceService::class);
    $this->actingAs($this->teacher);
});

function absenceProof(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('surat-tugas.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
}

it('submits a private proof for the authenticated GTK without recording attendance yet', function () {
    $this->post(route('gtk.absences.store'), $this->data + ['gtk_id' => $this->other->gtk_id, 'status' => 'approved', 'reviewed_by' => $this->teacher->id, 'evidence' => absenceProof()])->assertRedirect(route('gtk.absences.index'));
    $absence = GtkAbsenceRequest::firstOrFail();
    expect($absence->gtk_id)->toBe($this->teacher->gtk_id)
        ->and($absence->status)->toBe('pending')->and($absence->reviewed_by)->toBeNull()
        ->and(GtkAttendance::count())->toBe(0)->and(GtkWorkAudit::where('event', 'absence_submitted')->count())->toBe(1);
    Storage::disk('local')->assertExists($absence->evidence_path);
});

it('renders the form, private history, detail and headmaster queue', function () {
    $absence = $this->service->submit($this->teacher, $this->data, absenceProof());
    $this->get(route('gtk.absences.create'))->assertOk()->assertSee('Jenis Absen')->assertSee('500 KB');
    $this->get(route('gtk.absences.index'))->assertOk()->assertSee('Mengikuti rapat koordinasi')->assertSee('Diajukan');
    $this->get(route('gtk.absences.show', $absence))->assertOk()->assertSee('Batalkan Pengajuan');
    $this->actingAs($this->other)->get(route('gtk.absences.index'))->assertOk()->assertDontSee('Mengikuti rapat koordinasi');
    $this->actingAs($this->headmaster)->get(route('headmaster.absences.index'))->assertOk()->assertSee('GTK teacher');
    $this->get(route('gtk.absences.show', $absence))->assertOk()->assertSee('Simpan Keputusan');
});

it('requires valid type dates description and evidence', function (array $changes, string $field) {
    $this->post(route('gtk.absences.store'), array_replace($this->data + ['evidence' => absenceProof()], $changes))->assertSessionHasErrors($field);
    expect(GtkAbsenceRequest::count())->toBe(0);
})->with([
    [['type' => 'unknown'], 'type'],
    [['description' => '   '], 'description'],
    [['end_date' => '2026-10-31'], 'end_date'],
    [['start_date' => '2026-02-30'], 'start_date'],
    [['evidence' => null], 'evidence'],
    [['evidence' => UploadedFile::fake()->create('large.pdf', 501, 'application/pdf')], 'evidence'],
    [['evidence' => UploadedFile::fake()->createWithContent('attack.pdf', '<?php echo 1;')->mimeType('text/x-php')], 'evidence'],
    [['evidence' => UploadedFile::fake()->createWithContent('proof.php', "%PDF-1.4\n%%EOF")->mimeType('application/pdf')], 'evidence'],
]);

it('accepts supported proof formats at the upload limit', function (string $extension) {
    $proof = $extension === 'pdf' ? UploadedFile::fake()->create('proof.pdf', 500, 'application/pdf') : UploadedFile::fake()->image('proof.'.$extension)->size(500);
    $absence = $this->service->submit($this->teacher, $this->data, $proof);
    Storage::disk('local')->assertExists($absence->evidence_path);
})->with(['jpg', 'jpeg', 'png', 'pdf']);

it('rejects overlapping ranges inclusively while allowing adjacent dates', function () {
    $this->service->submit($this->teacher, $this->data, absenceProof());
    $this->post(route('gtk.absences.store'), array_replace($this->data, ['start_date' => '2026-11-02', 'end_date' => '2026-11-04']) + ['evidence' => absenceProof()])->assertSessionHasErrors('start_date');
    $this->service->submit($this->teacher, array_replace($this->data, ['start_date' => '2026-11-03', 'end_date' => '2026-11-03']), absenceProof());
    expect(GtkAbsenceRequest::count())->toBe(2)->and(Storage::disk('local')->allFiles('gtk-absence-evidence'))->toHaveCount(2);
});

it('approves all dates atomically and includes the result in the existing attendance report', function (string $type, string $status) {
    $absence = $this->service->submit($this->teacher, array_replace($this->data, ['type' => $type]), absenceProof());
    $this->actingAs($this->headmaster)->post(route('headmaster.absences.decide', $absence), ['status' => 'approved', 'review_notes' => 'Bukti sesuai'])->assertRedirect();
    expect($absence->fresh()->status)->toBe('approved')->and(GtkAttendance::count())->toBe(2)
        ->and(GtkAttendance::where('status', $status)->count())->toBe(2)->and(GtkDailyApproval::count())->toBe(0)
        ->and(GtkAttendance::first()->check_in_at)->toBeNull()->and(GtkAttendance::first()->gtk_absence_request_id)->toBe($absence->id);
    $rows = app(GtkReportService::class)->data($this->teacher->gtk, today(), 'attendance')['rows'];
    expect($rows->first()['attendance']->status)->toBe($status)->and($rows->first()['classification']['status'])->toBe($status);
})->with([['S', 'sick'], ['C3', 'sick'], ['C9', 'leave'], ['DL', 'leave'], ['I', 'leave'], ['TB', 'leave'], ['TK', 'absent'], ['L', 'leave']]);

it('allows future multi-month leave and filters by overlapping month', function () {
    $absence = $this->service->submit($this->teacher, array_replace($this->data, ['start_date' => '2026-11-30', 'end_date' => '2026-12-02']), absenceProof());
    $this->service->decide($this->headmaster, $absence, ['status' => 'approved']);
    expect(GtkAttendance::count())->toBe(3);
    $this->get(route('gtk.absences.index', ['month' => '2026-12']))->assertOk()->assertSee('Mengikuti rapat koordinasi');
    $this->get(route('gtk.absences.index', ['month' => '2027-01']))->assertOk()->assertDontSee('Mengikuti rapat koordinasi');
});

it('requires rejection notes and permits a replacement after rejection', function () {
    $absence = $this->service->submit($this->teacher, $this->data, absenceProof());
    $this->actingAs($this->headmaster)->post(route('headmaster.absences.decide', $absence), ['status' => 'rejected'])->assertSessionHasErrors('review_notes');
    $this->post(route('headmaster.absences.decide', $absence), ['status' => 'rejected', 'review_notes' => 'Surat tugas tidak sesuai tanggal'])->assertRedirect();
    expect($absence->fresh()->status)->toBe('rejected')->and(GtkAttendance::count())->toBe(0);
    $this->service->submit($this->teacher, $this->data, absenceProof());
    expect(GtkAbsenceRequest::count())->toBe(2);
});

it('restricts proof detail cancellation and decisions by identity and role', function () {
    $absence = $this->service->submit($this->teacher, $this->data, absenceProof());
    $this->get(route('gtk.absences.evidence', $absence))->assertOk()->assertDownload('bukti-absensi-'.$absence->id.'.pdf');
    foreach ([$this->other, $this->staff, $this->admin] as $actor) {
        $this->actingAs($actor)->get(route('gtk.absences.evidence', $absence))->assertForbidden();
        $this->get(route('gtk.absences.show', $absence))->assertForbidden();
        $this->delete(route('gtk.absences.cancel', $absence))->assertForbidden();
        $this->post(route('headmaster.absences.decide', $absence), ['status' => 'approved'])->assertForbidden();
    }
    $this->actingAs($this->headmaster)->get(route('gtk.absences.evidence', $absence))->assertOk();
    $this->delete(route('gtk.absences.cancel', $absence))->assertForbidden();
});

it('blocks self approval including another account connected to the same GTK', function () {
    $absence = $this->service->submit($this->headmaster, $this->data, absenceProof());
    $this->actingAs($this->headmaster)->post(route('headmaster.absences.decide', $absence), ['status' => 'approved'])->assertForbidden();
    $second = User::factory()->create(['role' => 'headmaster', 'gtk_id' => $this->headmaster->gtk_id]);
    $this->actingAs($second)->post(route('headmaster.absences.decide', $absence), ['status' => 'approved'])->assertForbidden();
});

it('cancels only pending requests, preserves history and proof, and releases the dates', function () {
    $absence = $this->service->submit($this->teacher, $this->data, absenceProof());
    $this->delete(route('gtk.absences.cancel', $absence))->assertRedirect();
    expect($absence->fresh()->status)->toBe('cancelled')->and(GtkAttendance::count())->toBe(0);
    Storage::disk('local')->assertExists($absence->evidence_path);
    $this->service->submit($this->teacher, $this->data, absenceProof());
    $this->actingAs($this->headmaster)->post(route('headmaster.absences.decide', $absence), ['status' => 'approved'])->assertSessionHasErrors('absence');
});

it('rejects repeated decisions and cancellation after approval', function () {
    $absence = $this->service->submit($this->teacher, $this->data, absenceProof());
    $this->service->decide($this->headmaster, $absence, ['status' => 'approved']);
    $this->delete(route('gtk.absences.cancel', $absence))->assertSessionHasErrors('absence');
    $this->actingAs($this->headmaster)->post(route('headmaster.absences.decide', $absence), ['status' => 'rejected', 'review_notes' => 'Ubah keputusan'])->assertSessionHasErrors('absence');
    expect(GtkAttendance::count())->toBe(2)->and($absence->fresh()->status)->toBe('approved');
});

it('never overwrites existing attendance at submission or approval, including a later conflict', function () {
    $absence = $this->service->submit($this->teacher, $this->data, absenceProof());
    GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => '2026-11-02', 'status' => 'present', 'check_in_at' => now()]);
    $this->actingAs($this->headmaster)->post(route('headmaster.absences.decide', $absence), ['status' => 'approved'])->assertSessionHasErrors('start_date');
    expect($absence->fresh()->status)->toBe('pending')->and(GtkAttendance::count())->toBe(1)->and(GtkDailyApproval::count())->toBe(0);
    $this->service->cancel($this->teacher, $absence);
    $this->actingAs($this->teacher)->post(route('gtk.absences.store'), $this->data + ['evidence' => absenceProof()])->assertSessionHasErrors('start_date');
});

it('protects approved absence from GPS and manual attendance changes', function () {
    $absence = $this->service->submit($this->teacher, $this->data, absenceProof());
    $this->service->decide($this->headmaster, $absence, ['status' => 'approved']);
    SchoolSetting::create(['school_name' => 'Madrasah', 'latitude' => -7.5, 'longitude' => 109.5, 'attendance_radius' => 100]);
    Livewire::test('gtk.attendance.index')->call('checkIn', -7.5, 109.5, 5)->assertSet('errorMessage', fn ($message) => str_contains($message, 'tidak dapat menimpa'));
    $attendance = GtkAttendance::firstOrFail();
    expect(fn () => $attendance->update(['status' => 'present']))->toThrow(ValidationException::class);
    expect(fn () => $attendance->fresh()->delete())->toThrow(ValidationException::class);
    expect($attendance->fresh()->status)->toBe('leave');
});

it('denies unlinked inactive GTK and guests', function () {
    $unlinked = User::factory()->create(['role' => 'teacher', 'gtk_id' => null]);
    $this->actingAs($unlinked)->get(route('gtk.absences.create'))->assertForbidden();
    $this->post(route('gtk.absences.store'), $this->data + ['evidence' => absenceProof()])->assertForbidden();
    $this->teacher->gtk->update(['is_active' => false]);
    $this->actingAs($this->teacher)->post(route('gtk.absences.store'), $this->data + ['evidence' => absenceProof()])->assertForbidden();
    auth()->forgetGuards();
    $this->get(route('gtk.absences.index'))->assertRedirect(route('login'));
});

it('keeps admin attendance monitoring and filters while recording absences only after headmaster approval', function () {
    $absence = $this->service->submit($this->teacher, array_replace($this->data, ['type' => 'S']), absenceProof());
    $this->service->submit($this->staff, $this->data, absenceProof());
    $this->service->decide($this->headmaster, $absence, ['status' => 'approved']);
    $present = GtkAttendance::create(['gtk_id' => $this->other->gtk_id, 'attendance_date' => today(),
        'status' => 'present', 'check_in_at' => today()->setTime(7, 0), 'check_out_at' => today()->setTime(14, 0)]);
    $this->actingAs($this->admin)->get(route('admin.gtk-attendances.index'))
        ->assertOk()->assertSee('Monitoring Presensi GTK')->assertSee('pengajuan GTK setelah disetujui kepala madrasah')
        ->assertDontSee('Update Izin / Cuti / Sakit')->assertDontSee('Update Status Presensi')
        ->assertDontSee('openStatusModal')->assertDontSee('wire:click="updateStatus"', false);
    $component = Livewire::test('admin.gtk-attendances.index')
        ->assertViewHas('sickCount', 1)->assertViewHas('presentCount', 1)
        ->assertViewHas('notAttendedGtks', fn ($gtks) => $gtks->contains('id', $this->staff->gtk_id))
        ->assertViewHas('attendances', fn ($rows) => $rows->total() === 2)
        ->set('status', 'sick')->assertViewHas('attendances', fn ($rows) => $rows->total() === 1 && $rows->first()->gtk_id === $this->teacher->gtk_id)
        ->set('gtkId', (string) $this->other->gtk_id)->assertViewHas('attendances', fn ($rows) => $rows->total() === 0)
        ->set('status', 'present')->assertViewHas('attendances', fn ($rows) => $rows->first()->id === $present->id)
        ->set('date', '2026-11-01')->assertViewHas('attendances', fn ($rows) => $rows->total() === 0)
        ->call('resetFilters')->assertSet('date', '2026-11-02')->assertSet('gtkId', '')->assertSet('status', '')
        ->assertViewHas('attendances', fn ($rows) => $rows->total() === 2);
    expect(GtkAttendance::where('gtk_id', $this->staff->gtk_id)->exists())->toBeFalse();
    $this->actingAs($this->teacher)->get(route('admin.gtk-attendances.index'))->assertForbidden();
});

it('rejects direct calls to removed admin attendance mutation actions without changing records', function (string $method, ?string $status) {
    if ($status !== null) {
        GtkAttendance::create(['gtk_id' => $this->teacher->gtk_id, 'attendance_date' => today(), 'status' => $status]);
    }
    $this->actingAs($this->admin);
    $before = DB::table('gtk_attendances')->orderBy('id')->get()->toJson();
    $component = Livewire::test('admin.gtk-attendances.index');
    expect(fn () => $component->call($method, $this->teacher->gtk_id))->toThrow(MethodNotFoundException::class);
    expect(DB::table('gtk_attendances')->orderBy('id')->get()->toJson())->toBe($before);
})->with(['updateStatus', 'resetAttendanceStatus'])->with([null, 'present', 'leave', 'sick']);
