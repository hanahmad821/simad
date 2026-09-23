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
        Schema::create('incoming_letters', function (Blueprint $table) {
            $table->id();

            $table->string('nomor_surat');
            $table->string('pengirim');
            $table->date('tanggal_surat');
            $table->date('tanggal_masuk');
            $table->string('perihal');
            $table->string('file')->nullable();
            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->index('tanggal_masuk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_letters');
    }
};
