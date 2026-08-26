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
        Schema::create('student_class_histories', function (Blueprint $table) {
            $table->id();

            // Siswa
            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            // Kelas
            $table->foreignId('school_class_id')
                ->constrained('school_classes')
                ->restrictOnDelete();

            // Tahun pelajaran
            $table->foreignId('academic_year_id')
                ->constrained('academic_years')
                ->restrictOnDelete();

            // Semester
            $table->foreignId('semester_id')
                ->constrained('semesters')
                ->restrictOnDelete();

            // Status siswa pada kelas tersebut
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('student_id');
            $table->index('school_class_id');
            $table->index('academic_year_id');
            $table->index('semester_id');
            $table->index('is_active');

            /*
             * Satu siswa hanya boleh memiliki
             * satu riwayat kelas pada kombinasi
             * tahun pelajaran + semester.
             */
            $table->unique(
                [
                    'student_id',
                    'academic_year_id',
                    'semester_id',
                ],
                'sch_unique_student_period'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_class_histories');
    }
};
