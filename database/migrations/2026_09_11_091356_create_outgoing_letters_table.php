<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('outgoing_letters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('letter_type_id')
                ->constrained('letter_types')
                ->restrictOnDelete();

            // Nomor urut global dalam satu tahun
            $table->unsignedInteger('nomor_urut');

            $table->unsignedSmallInteger('nomor_tahun');

            // Contoh: A.001/LPM/33-23/MA-03/IX/2026
            $table->string('nomor_surat')->unique();

            $table->string('tujuan');
            $table->date('tanggal_surat');
            $table->string('perihal');

            $table->longText('isi')->nullable();

            // Draft surat / hasil upload
            $table->string('file')->nullable();

            $table->text('keterangan')->nullable();

            $table->enum('status', [
                'draft',
                'issued',
                'cancelled',
            ])->default('draft');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(
                ['nomor_urut', 'nomor_tahun'],
                'ol_unique_number_year'
            );

            $table->index('tanggal_surat');
            $table->index('letter_type_id');
            $table->index('status');
            $table->index('nomor_tahun');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outgoing_letters');
    }
};
