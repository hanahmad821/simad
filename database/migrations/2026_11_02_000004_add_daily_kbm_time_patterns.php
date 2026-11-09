<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kbm_day_patterns', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_of_week')->unique();
            $table->time('morning_start');
            $table->time('morning_end');
            $table->unsignedTinyInteger('teaching_periods');
            $table->json('breaks');
            $table->timestamps();
        });
        Schema::table('lesson_periods', function (Blueprint $table) {
            $table->dropUnique(['period_number']);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('slot_order')->nullable();
            $table->unique(['day_of_week', 'name'], 'lesson_periods_day_name_unique');
            $table->index(['day_of_week', 'slot_order'], 'lesson_periods_day_order_index');
        });
    }

    public function down(): void
    {
        if (DB::table('lesson_periods')->whereNotNull('day_of_week')->exists()) {
            throw new RuntimeException('Hapus pola jam harian beserta jadwal terkait sebelum mengembalikan skema jam global.');
        }
        Schema::table('lesson_periods', function (Blueprint $table) {
            $table->dropUnique('lesson_periods_day_name_unique');
            $table->dropIndex('lesson_periods_day_order_index');
            $table->dropColumn(['day_of_week', 'slot_order']);
            $table->unique('period_number');
        });
        Schema::dropIfExists('kbm_day_patterns');
    }
};
