<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('exit_status', 20)->nullable()->index();
            $table->date('exit_date')->nullable();
            $table->foreignId('exit_academic_year_id')->nullable()->constrained('academic_years')->restrictOnDelete();
        });
        Schema::create('student_progressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_history_id')->constrained('student_class_histories')->restrictOnDelete();
            $table->foreignId('destination_history_id')->nullable()->constrained('student_class_histories')->restrictOnDelete();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->string('action', 20);
            $table->date('effective_date');
            $table->timestamps();
            $table->unique('source_history_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_progressions');
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exit_academic_year_id');
            $table->dropColumn(['exit_status', 'exit_date']);
        });
    }
};
