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
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();

            // GTK
            $table->foreignId('gtk_id')
                ->constrained('gtks')
                ->cascadeOnDelete();

            // Mata pelajaran
            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->restrictOnDelete();

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

            // Beban jam mengajar per minggu
            $table->unsignedTinyInteger('teaching_hours')->default(0);

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            /*
             * Satu guru tidak boleh mempunyai
             * penugasan yang sama dua kali.
             */
            $table->unique(
                [
                    'gtk_id',
                    'subject_id',
                    'school_class_id',
                    'academic_year_id',
                    'semester_id',
                ],
                'ta_unique_assignment'
            );

            $table->index('gtk_id');
            $table->index('subject_id');
            $table->index('school_class_id');
            $table->index('academic_year_id');
            $table->index('semester_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
    }
};
