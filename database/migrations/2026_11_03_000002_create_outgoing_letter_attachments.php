<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outgoing_letter_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outgoing_letter_id')->constrained('outgoing_letters')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path')->unique();
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('outgoing_letter_attachments')->exists()) {
            throw new RuntimeException('Arsip lampiran harus dipindahkan sebelum rollback.');
        }
        Schema::dropIfExists('outgoing_letter_attachments');
    }
};
