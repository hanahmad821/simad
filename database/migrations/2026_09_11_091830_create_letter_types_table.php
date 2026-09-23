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
        Schema::create('letter_types', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('code', 10);

            // Template surat, nullable karena tidak semua jenis
            // harus memiliki template.
            $table->string('template_file')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique('name');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_types');
    }
};
