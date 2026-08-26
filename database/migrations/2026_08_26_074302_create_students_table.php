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
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            // Identitas siswa
            $table->string('student_code', 30)->unique();

            $table->string('nis', 30)->nullable()->unique();
            $table->string('nisn', 30)->nullable()->unique();
            $table->string('nik', 30)->nullable()->unique();

            $table->string('full_name', 150);

            // Identitas pribadi
            $table->enum('gender', [
                'male',
                'female',
            ])->nullable();

            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();

            // Orang tua
            $table->string('father_name', 150)->nullable();
            $table->string('mother_name', 150)->nullable();

            // Wali
            $table->string('guardian_name', 150)->nullable();
            $table->string('guardian_phone', 30)->nullable();

            // Alamat
            $table->text('address')->nullable();

            // PIP
            $table->boolean('pip_status')->default(false);
            $table->string('pip_number', 50)->nullable();

            // KIP
            $table->boolean('kip_status')->default(false);
            $table->string('kip_number', 50)->nullable();

            // Status siswa
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Index
            $table->index('full_name');
            $table->index('gender');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
