<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
            $table->string('signature_method')->default('qr');
            $table->string('signed_file')->nullable();
            $table->timestamp('signed_uploaded_at')->nullable();
            $table->foreignId('signed_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('outgoing_letters')->where('signature_method', 'wet')->exists()) {
            throw new RuntimeException('Arsip tanda tangan basah harus dipindahkan sebelum rollback.');
        }
        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signed_uploaded_by');
            $table->dropColumn(['signature_method', 'signed_file', 'signed_uploaded_at']);
        });
    }
};
