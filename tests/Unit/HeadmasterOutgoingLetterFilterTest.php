<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Filter tests require an isolated in-memory database.');
    }
    // Minimal fixture schema for the list; legacy migrations contain duplicates.
    Schema::create('outgoing_letters', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('letter_type_id')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->string('nomor_surat');
        $table->string('jenis_surat')->default('Surat Manual');
        $table->string('tujuan')->default('Dinas Pendidikan');
        $table->string('perihal')->default('Rapat koordinasi');
        $table->date('tanggal_surat');
        $table->string('status');
        $table->timestamp('approved_at')->nullable();
        $table->timestamps();
    });
    $this->actingAs(User::factory()->make(['id' => 1, 'role' => 'headmaster']));
});

function filterLetter(string $number, string $status): void
{
    DB::table('outgoing_letters')->insert([
        'nomor_surat' => $number, 'status' => $status, 'tanggal_surat' => today(),
        'approved_at' => $status === 'issued' ? now() : null, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('filters pending and issued letters and excludes unsubmitted drafts', function () {
    filterLetter('PENDING-001', 'pending');
    filterLetter('ISSUED-001', 'issued');
    filterLetter('DRAFT-001', 'draft');
    filterLetter('CANCELLED-001', 'cancelled');
    Livewire::test('headmaster.outgoing-letters.index')
        ->assertSet('status', 'pending')->assertSee('PENDING-001')->assertDontSee('ISSUED-001')
        ->set('status', 'issued')->assertSee('ISSUED-001')->assertSee('Detail')->assertDontSee('PENDING-001')
        ->set('status', '')->assertSee('PENDING-001')->assertSee('ISSUED-001')
        ->assertDontSee('DRAFT-001')->assertDontSee('CANCELLED-001');
});

it('combines search with the issued filter', function () {
    filterLetter('ISSUED-MATCH', 'issued');
    filterLetter('ISSUED-OTHER', 'issued');
    filterLetter('PENDING-MATCH', 'pending');
    Livewire::test('headmaster.outgoing-letters.index')->set('status', 'issued')->set('search', 'MATCH')
        ->assertSee('ISSUED-MATCH')->assertDontSee('ISSUED-OTHER')->assertDontSee('PENDING-MATCH')
        ->set('search', 'NO-SUCH-LETTER')->assertSee('Tidak ada surat yang sesuai');
});

it('resets pagination when status changes', function () {
    for ($index = 1; $index <= 11; $index++) {
        filterLetter('PENDING-'.$index, 'pending');
    }
    filterLetter('ISSUED-ONLY', 'issued');
    Livewire::test('headmaster.outgoing-letters.index')->call('setPage', 2)->assertSet('paginators.page', 2)
        ->set('status', 'issued')->assertSet('paginators.page', 1)->assertSee('ISSUED-ONLY');
});
