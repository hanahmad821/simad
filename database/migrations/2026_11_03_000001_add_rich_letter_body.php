<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->string('isi_format', 10)->default('text')->after('isi');
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('outgoing_letters')->where('isi_format', 'html')->exists()) {
            throw new RuntimeException('Surat berformat rich text harus dikonversi sebelum rollback agar formatnya tetap tersimpan.');
        }
        Schema::table('outgoing_letters', fn (Blueprint $table) => $table->dropColumn('isi_format'));
    }
};
