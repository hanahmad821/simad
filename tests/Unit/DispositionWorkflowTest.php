<?php

use App\Models\Gtk;
use App\Models\IncomingLetter;
use App\Models\LetterDisposition;
use App\Models\User;
use App\Services\LetterDispositionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    // Only run the migrations needed for this workflow. The legacy migration
    // history contains duplicate table creations unrelated to dispositions.
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Disposition tests require an isolated in-memory database.');
    }
    foreach ([
        '0001_01_01_000000_create_users_table.php',
        '2025_08_14_170933_add_two_factor_columns_to_users_table.php',
        '2026_08_26_064935_create_gtks_table.php',
        '2026_09_08_042246_add_role_and_gtk_id_to_users_table.php', '2026_11_05_000001_add_secondary_role_to_users.php',
        '2026_08_26_081832_create_school_settings_table.php',
        '2026_09_11_024249_create_incoming_letters_table.php',
        '2026_09_11_025419_create_letter_dispositions_table.php',
    ] as $migration) {
        (require database_path('migrations/'.$migration))->up();
    }
});

function dispositionUser(string $role, bool $active = true, bool $linked = true): User
{
    $gtk = $linked ? Gtk::create(['full_name' => fake()->name(), 'is_active' => $active]) : null;

    return User::factory()->create(['role' => $role, 'gtk_id' => $gtk?->id]);
}

function dispositionLetter(): IncomingLetter
{
    return IncomingLetter::create([
        'nomor_surat' => 'SM-'.fake()->unique()->numerify('######'),
        'pengirim' => 'Dinas Pendidikan',
        'tanggal_surat' => today(),
        'tanggal_masuk' => today(),
        'perihal' => 'Undangan rapat koordinasi',
    ]);
}

function sendDisposition(IncomingLetter $letter, User $headmaster, User $recipient): LetterDisposition
{
    return app(LetterDispositionService::class)->send($letter, $headmaster, [
        'toUserId' => $recipient->id, 'instruction' => 'Hadiri rapat dan laporkan hasilnya.', 'deadline' => null,
    ]);
}

it('allows only the headmaster to send a disposition', function (string $role) {
    $letter = dispositionLetter();
    $sender = dispositionUser($role);
    $recipient = dispositionUser('teacher');
    try {
        sendDisposition($letter, $sender, $recipient);
        test()->fail('Unauthorized sender was accepted.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403)->and($letter->dispositions()->count())->toBe(0);
    }
})->with(['admin', 'teacher', 'staff']);

it('accepts active teachers and staff as recipients', function (string $role) {
    $headmaster = dispositionUser('headmaster');
    $recipient = dispositionUser($role);
    $letter = dispositionLetter();
    $disposition = sendDisposition($letter, $headmaster, $recipient);

    expect($disposition->from_user_id)->toBe($headmaster->id)
        ->and($disposition->to_user_id)->toBe($recipient->id)
        ->and($disposition->incoming_letter_id)->toBe($letter->id)
        ->and($disposition->status)->toBe('pending');
})->with(['teacher', 'staff']);

it('keeps an admin with a second teacher role eligible for dispositions', function () {
    $recipient = dispositionUser('admin');
    $recipient->update(['secondary_role' => 'teacher']);
    $disposition = sendDisposition(dispositionLetter(), dispositionUser('headmaster'), $recipient);
    expect($disposition->to_user_id)->toBe($recipient->id);
});

it('rejects inactive, unlinked, or ineligible recipients even with a forged ID', function (string $role, bool $active, bool $linked) {
    $headmaster = dispositionUser('headmaster');
    $recipient = dispositionUser($role, $active, $linked);
    $letter = dispositionLetter();
    try {
        sendDisposition($letter, $headmaster, $recipient);
        test()->fail('Ineligible recipient was accepted.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('toUserId')->and($letter->dispositions()->count())->toBe(0);
    }
})->with([
    ['teacher', false, true], ['staff', true, false], ['admin', true, true], ['headmaster', true, true],
]);

it('validates instructions and deadlines', function (string $instruction, ?string $deadline, string $field) {
    $recipient = dispositionUser('teacher');
    try {
        app(LetterDispositionService::class)->send(dispositionLetter(), dispositionUser('headmaster'), [
            'toUserId' => $recipient->id, 'instruction' => $instruction, 'deadline' => $deadline,
        ]);
        test()->fail('Invalid disposition was accepted.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field)->and(LetterDisposition::count())->toBe(0);
    }
})->with([
    ['   ', null, 'instruction'],
    ['Instruksi', '2000-01-01', 'deadline'],
]);

it('runs admin input, headmaster disposition, recipient follow-up, and admin monitoring end to end', function () {
    $admin = dispositionUser('admin');
    $headmaster = dispositionUser('headmaster');
    $recipient = dispositionUser('teacher');
    Livewire::actingAs($admin)->test('admin.incoming-letters.index')
        ->call('create')->set('nomor_surat', 'SM-WORKFLOW-001')->set('pengirim', 'Dinas Pendidikan')
        ->set('perihal', 'Rapat koordinasi')->call('save')->assertHasNoErrors()->assertSee('Menunggu disposisi');
    $letter = IncomingLetter::where('nomor_surat', 'SM-WORKFLOW-001')->firstOrFail();
    Livewire::actingAs($headmaster)->test('headmaster.incoming-letters.index')->assertSee('SM-WORKFLOW-001');
    Livewire::actingAs($headmaster)->test('headmaster.incoming-letters.dispositions', ['incomingLetter' => $letter])
        ->call('openModal')->set('toUserId', (string) $recipient->id)->set('instruction', 'Hadiri rapat dan laporkan hasilnya.')
        ->call('save')->assertHasNoErrors()->assertSet('showModal', false)->assertSee('Menunggu dibaca');
    $disposition = $letter->dispositions()->firstOrFail();
    $page = Livewire::actingAs($recipient)->test('gtk.dispositions.show', ['disposition' => $disposition]);
    expect($disposition->fresh()->status)->toBe('read')->and($disposition->fresh()->read_at)->not->toBeNull();
    $page->call('startProcessing')->assertHasNoErrors()->set('notes', 'Rapat dihadiri. Laporan sudah diserahkan.')
        ->call('complete')->assertHasNoErrors()->assertSee('Selesai');
    expect($disposition->fresh()->status)->toBe('completed')->and($disposition->fresh()->completed_at)->not->toBeNull();
    Livewire::actingAs($admin)->test('admin.incoming-letters.dispositions', ['incomingLetter' => $letter])
        ->assertSee('Rapat dihadiri. Laporan sudah diserahkan.')->assertSee('Selesai')->assertDontSee('Buat Disposisi');
    Livewire::actingAs($headmaster)->test('headmaster.incoming-letters.index')->set('status', 'completed')->assertSee('SM-WORKFLOW-001');
});

it('does not expose creation actions on the admin monitoring component', function () {
    $component = app('livewire')->new('admin.incoming-letters.dispositions');
    expect(method_exists($component, 'save'))->toBeFalse()->and(method_exists($component, 'openModal'))->toBeFalse();
});

it('does not permit skipping processing or reopening a completed disposition', function () {
    $recipient = dispositionUser('staff');
    $disposition = sendDisposition(dispositionLetter(), dispositionUser('headmaster'), $recipient);
    $service = app(LetterDispositionService::class);
    try {
        $service->complete($disposition, $recipient, 'Skipped processing');
        test()->fail('Completion before processing was accepted.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('status')->and($disposition->fresh()->status)->toBe('pending');
    }
    $service->markRead($disposition, $recipient);
    $service->start($disposition, $recipient);
    $service->complete($disposition, $recipient, 'Selesai');
    try {
        $service->start($disposition, $recipient);
        test()->fail('Completed disposition was reopened.');
    } catch (ValidationException $e) {
        expect($disposition->fresh()->status)->toBe('completed')->and($disposition->fresh()->notes)->toBe('Selesai');
    }
});

it('checks ownership again on follow-up actions', function (string $action) {
    $recipient = dispositionUser('teacher');
    $other = dispositionUser('staff');
    $disposition = sendDisposition(dispositionLetter(), dispositionUser('headmaster'), $recipient);
    $service = app(LetterDispositionService::class);
    $service->markRead($disposition, $recipient);
    try {
        if ($action === 'start') {
            $service->start($disposition, $other);
        } else {
            $service->complete($disposition, $other, 'Forged result');
        }
        test()->fail('Another recipient changed the disposition.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403)->and($disposition->fresh()->status)->toBe('read');
    }
})->with(['start', 'complete']);

it('protects headmaster routes from admin and teacher accounts', function (string $role) {
    $this->actingAs(dispositionUser($role));
    $this->get(route('headmaster.incoming-letters.index'))->assertForbidden();
})->with(['admin', 'teacher']);

it('shows only assigned dispositions to a recipient', function () {
    $headmaster = dispositionUser('headmaster');
    $recipient = dispositionUser('teacher');
    $other = dispositionUser('staff');
    $mine = dispositionLetter();
    $theirs = dispositionLetter();
    sendDisposition($mine, $headmaster, $recipient);
    sendDisposition($theirs, $headmaster, $other);
    Livewire::actingAs($recipient)->test('gtk.dispositions.index')->assertSee($mine->nomor_surat)->assertDontSee($theirs->nomor_surat);
});

it('opens the new inbox and disposition pages for the headmaster', function () {
    $this->actingAs(dispositionUser('headmaster'));
    $letter = dispositionLetter();
    $this->get(route('headmaster.incoming-letters.index'))->assertOk()->assertSee('Surat Masuk &amp; Disposisi', false);
    $this->get(route('headmaster.incoming-letters.dispositions', $letter))->assertOk()->assertSee('Buat Disposisi');
});

it('allows the admin to monitor dispositions through the existing menu', function () {
    $this->actingAs(dispositionUser('admin'));
    $letter = dispositionLetter();
    $this->get(route('admin.incoming-letters.dispositions', $letter))
        ->assertOk()->assertSee('Pantau Disposisi Surat')->assertDontSee('Buat Disposisi');
});

it('does not let a recipient open another recipients disposition', function () {
    $recipient = dispositionUser('teacher');
    $other = dispositionUser('staff');
    $disposition = sendDisposition(dispositionLetter(), dispositionUser('headmaster'), $recipient);
    $this->actingAs($other);
    $this->get(route('gtk.dispositions.show', $disposition))->assertForbidden();
    expect($disposition->fresh()->status)->toBe('pending')->and($disposition->fresh()->read_at)->toBeNull();
});

it('keeps a letter ongoing until every recipient has completed their disposition', function () {
    $headmaster = dispositionUser('headmaster');
    $first = dispositionUser('teacher');
    $second = dispositionUser('staff');
    $letter = dispositionLetter();
    $service = app(LetterDispositionService::class);
    $one = sendDisposition($letter, $headmaster, $first);
    $two = sendDisposition($letter, $headmaster, $second);
    $service->markRead($one, $first);
    $service->start($one, $first);
    $service->complete($one, $first, 'Tindak lanjut guru selesai');
    Livewire::actingAs($headmaster)->test('headmaster.incoming-letters.index')->assertDontSee($letter->nomor_surat)
        ->set('status', 'ongoing')->assertSee($letter->nomor_surat)
        ->set('status', 'completed')->assertDontSee($letter->nomor_surat);
    $service->markRead($two, $second);
    $service->start($two, $second);
    $service->complete($two, $second, 'Tindak lanjut staf selesai');
    Livewire::actingAs($headmaster)->test('headmaster.incoming-letters.index')->set('status', 'completed')->assertSee($letter->nomor_surat);
});

it('allows staff to record and edit incoming letters and monitor dispositions', function () {
    $this->withoutVite();
    $staff = dispositionUser('staff');
    $this->actingAs($staff);
    $this->get(route('staff.incoming-letters.index'))->assertOk();
    Livewire::test('admin.incoming-letters.index')->call('create')
        ->set('nomor_surat', 'STAFF-001')->set('pengirim', 'Dinas Pendidikan')
        ->set('perihal', 'Undangan rapat')->call('save')->assertHasNoErrors();
    $letter = IncomingLetter::where('nomor_surat', 'STAFF-001')->firstOrFail();
    Livewire::test('admin.incoming-letters.index')->call('edit', $letter->id)
        ->set('perihal', 'Undangan rapat koordinasi')->call('save')->assertHasNoErrors();
    expect($letter->fresh()->perihal)->toBe('Undangan rapat koordinasi');
    $this->get(route('staff.incoming-letters.dispositions', $letter))->assertOk()
        ->assertSee(route('staff.incoming-letters.index'), false);
    $this->get(route('headmaster.incoming-letters.dispositions', $letter))->assertForbidden();
});

it('blocks teachers from staff incoming letter pages', function () {
    $this->actingAs(dispositionUser('teacher'));
    $this->get(route('staff.incoming-letters.index'))->assertForbidden();
    Livewire::test('admin.incoming-letters.index')->assertForbidden();
});
