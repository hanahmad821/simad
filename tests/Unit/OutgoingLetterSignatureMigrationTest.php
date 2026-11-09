<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

it('migrates existing letters to QR by default and supports the wet signature waiting status', function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') {
        throw new RuntimeException('Migration tests require an isolated database.');
    }
    Schema::create('users', fn (Blueprint $table) => $table->id());
    Schema::create('outgoing_letters', function (Blueprint $table) {
        $table->id();
        $table->enum('status', ['draft', 'issued', 'cancelled'])->default('draft');
    });
    DB::table('outgoing_letters')->insert(['status' => 'issued']);
    $migration = require database_path('migrations/2026_10_27_000001_add_signature_method_to_outgoing_letters.php');
    $migration->up();
    expect(DB::table('outgoing_letters')->value('signature_method'))->toBe('qr');
    DB::table('outgoing_letters')->insert(['status' => 'awaiting_signature', 'signature_method' => 'wet']);
    expect(DB::table('outgoing_letters')->where('status', 'awaiting_signature')->count())->toBe(1);
});
