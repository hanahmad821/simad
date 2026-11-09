<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Deployment migration tests require an isolated in-memory database.');
    }
});

function deploymentArchiveFixtures(): void
{
    DB::table('users')->insert(['id' => 1, 'name' => 'Migration QA', 'email' => 'migration-qa@example.invalid', 'password' => 'test-only']);
    DB::table('letter_types')->insert(['id' => 1, 'name' => 'Jenis QA', 'code' => 'E']);
    foreach ([1, 2] as $id) {
        DB::table('outgoing_letters')->insert(['id' => $id, 'letter_type_id' => $id === 1 ? 1 : null,
            'nomor_urut' => $id, 'nomor_tahun' => 2026, 'nomor_surat' => 'QA.00'.$id.'/2026',
            'jenis_surat' => 'Nama arsip asli', 'use_template' => $id !== 1, 'tujuan' => 'Penerima QA',
            'tanggal_surat' => '2026-11-03', 'perihal' => 'Uji migration', 'isi' => 'Isi arsip tetap', 'created_by' => 1,
            'status' => 'issued', 'file' => $id === 1 ? 'manual/source.pdf' : null,
            'final_file' => $id === 1 ? 'archive/original.PDF' : 'archive/original.docx',
            'final_pdf_file' => $id === 1 ? 'archive/already-approved.pdf' : null,
            'verification_token' => 'migration-qa-token-'.$id, 'data' => json_encode(['keterangan' => 'Data arsip tetap'])]);
    }
}

it('migrates the complete application from empty and does nothing on a second run', function () {
    deploymentArchiveFixtures();
    $before = DB::table('outgoing_letters')->orderBy('id')->get()->toArray();
    $ledger = DB::table('migrations')->count();
    $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    expect(DB::table('migrations')->count())->toBe($ledger)
        ->and(DB::table('outgoing_letters')->orderBy('id')->get()->toArray())->toEqual($before);
});

it('completes an old letter schema without losing its numbers contents tokens or original archive paths', function () {
    deploymentArchiveFixtures();
    Schema::table('outgoing_letters', fn (Blueprint $table) => $table->dropColumn(['jenis_surat', 'use_template', 'final_docx_file', 'final_pdf_file']));
    $migration = require database_path('migrations/2026_11_03_000003_complete_application_runtime_schema.php');
    $migration->up();
    $manual = DB::table('outgoing_letters')->find(1);
    $template = DB::table('outgoing_letters')->find(2);
    expect($manual->jenis_surat)->toBe('Jenis QA')->and((bool) $manual->use_template)->toBeFalse()
        ->and($manual->final_pdf_file)->toBe('archive/original.PDF')->and($manual->nomor_surat)->toBe('QA.001/2026')
        ->and($manual->verification_token)->toBe('migration-qa-token-1')->and($manual->isi)->toBe('Isi arsip tetap')
        ->and($template->jenis_surat)->toBe('Surat Keluar')->and((bool) $template->use_template)->toBeTrue()
        ->and($template->final_docx_file)->toBe('archive/original.docx');
    $before = DB::table('outgoing_letters')->orderBy('id')->get()->toArray();
    $migration->up();
    expect(DB::table('outgoing_letters')->orderBy('id')->get()->toArray())->toEqual($before);
});

it('keeps existing explicit archive metadata when the completion migration is applied', function () {
    deploymentArchiveFixtures();
    $migration = require database_path('migrations/2026_11_03_000003_complete_application_runtime_schema.php');
    $migration->up();
    $manual = DB::table('outgoing_letters')->find(1);
    expect($manual->jenis_surat)->toBe('Nama arsip asli')->and((bool) $manual->use_template)->toBeFalse()
        ->and($manual->final_pdf_file)->toBe('archive/already-approved.pdf')->and($manual->final_file)->toBe('archive/original.PDF');
});

it('attaches the outgoing letter foreign key after the type table exists', function () {
    deploymentArchiveFixtures();
    expect(fn () => DB::table('outgoing_letters')->where('id', 1)->update(['letter_type_id' => 999999]))->toThrow(QueryException::class);
});

it('stops on duplicate legacy teaching assignments while preserving both rows and their old index', function () {
    Schema::table('teaching_assignments', function (Blueprint $table) {
        $table->dropUnique('ta_unique_assignment');
        $table->unique(['gtk_id', 'subject_id', 'school_class_id', 'academic_year_id', 'semester_id'], 'ta_unique_assignment');
    });
    DB::table('gtks')->insert([['id' => 1, 'full_name' => 'Guru QA 1'], ['id' => 2, 'full_name' => 'Guru QA 2']]);
    DB::table('academic_years')->insert(['id' => 1, 'name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']);
    DB::table('semesters')->insert(['id' => 1, 'academic_year_id' => 1, 'name' => 'Ganjil', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31']);
    DB::table('school_classes')->insert(['id' => 1, 'name' => 'Kelas QA', 'grade' => 10]);
    DB::table('subjects')->insert(['id' => 1, 'code' => 'QA', 'name' => 'Mapel QA']);
    foreach ([1, 2] as $id) {
        DB::table('teaching_assignments')->insert(['id' => $id, 'gtk_id' => $id, 'subject_id' => 1, 'school_class_id' => 1, 'academic_year_id' => 1, 'semester_id' => 1]);
    }
    $migration = require database_path('migrations/2026_11_03_000003_complete_application_runtime_schema.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'penugasan ganda');
    expect(DB::table('teaching_assignments')->count())->toBe(2)
        ->and(Schema::hasIndex('teaching_assignments', ['gtk_id', 'subject_id', 'school_class_id', 'academic_year_id', 'semester_id'], 'unique'))->toBeTrue();
});
