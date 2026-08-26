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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();

            // Penugasan mengajar
            $table->foreignId('teaching_assignment_id')
                ->constrained('teaching_assignments')
                ->cascadeOnDelete();

            // Jam pelajaran
            $table->foreignId('lesson_period_id')
                ->constrained('lesson_periods')
                ->restrictOnDelete();

            // Ruangan
            $table->foreignId('room_id')
                ->constrained('rooms')
                ->restrictOnDelete();

            // Hari
            $table->unsignedTinyInteger('day_of_week');

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Index
            $table->index('teaching_assignment_id');
            $table->index('lesson_period_id');
            $table->index('room_id');
            $table->index('day_of_week');
            $table->index('is_active');

            // Satu assignment tidak boleh memiliki jadwal
            // yang sama persis pada hari dan jam yang sama.
            $table->unique(
                [
                    'teaching_assignment_id',
                    'day_of_week',
                    'lesson_period_id',
                ],
                'schedule_unique_assignment'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
