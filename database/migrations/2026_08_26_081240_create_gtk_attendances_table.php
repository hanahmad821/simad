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
        Schema::create('gtk_attendances', function (Blueprint $table) {
            $table->id();

            // GTK
            $table->foreignId('gtk_id')
                ->constrained('gtks')
                ->cascadeOnDelete();

            // Tanggal presensi
            $table->date('attendance_date');

            // Check In
            $table->timestamp('check_in_at')->nullable();
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->decimal('check_in_accuracy', 8, 2)->nullable();

            // Check Out
            $table->timestamp('check_out_at')->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->decimal('check_out_accuracy', 8, 2)->nullable();

            // Status
            $table->enum('status', [
                'present',
                'late',
                'absent',
                'leave',
                'sick',
            ])->default('present');

            // Catatan
            $table->text('notes')->nullable();

            $table->timestamps();

            // Satu GTK hanya boleh memiliki
            // satu presensi dalam satu hari.
            $table->unique(
                [
                    'gtk_id',
                    'attendance_date',
                ],
                'ga_unique_gtk_date'
            );

            $table->index('attendance_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gtk_attendances');
    }
};
