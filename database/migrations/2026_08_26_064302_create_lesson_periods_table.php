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
        Schema::create('lesson_periods', function (Blueprint $table) {
            $table->id();

            $table->unsignedTinyInteger('period_number');
            $table->string('name', 50);

            $table->time('start_time');
            $table->time('end_time');

            $table->boolean('is_break')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique('period_number');
            $table->index('is_break');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_periods');
    }
};
