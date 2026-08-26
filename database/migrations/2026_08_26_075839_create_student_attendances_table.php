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
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();

            // Jadwal KBM
            $table->foreignId('schedule_id')
                ->constrained('schedules')
                ->cascadeOnDelete();

            // Siswa
            $table->foreignId('student_id')
                ->constrained('students')
                ->restrictOnDelete();

            // Tanggal presensi
            $table->date('attendance_date');

            // Status kehadiran
            $table->enum('status', [
                'present',
                'late',
                'excused',
                'sick',
                'absent',
            ])->default('present');

            // Waktu scan / pencatatan
            $table->timestamp('scanned_at')->nullable();

            // User yang mencatat presensi
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Catatan
            $table->text('notes')->nullable();

            $table->timestamps();

            /*
             * Satu siswa hanya boleh memiliki
             * satu presensi pada satu jadwal
             * pada tanggal yang sama.
             */
            $table->unique(
                [
                    'schedule_id',
                    'student_id',
                    'attendance_date',
                ],
                'sa_unique_student_schedule_date'
            );

            $table->index('attendance_date');
            $table->index('status');
            $table->index('recorded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
    }
};
