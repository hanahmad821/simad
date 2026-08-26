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
        Schema::create('gtk_educations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gtk_id')
                ->constrained('gtks')
                ->cascadeOnDelete();

            $table->string('level', 50);
            $table->string('institution_name', 150);
            $table->string('major', 150)->nullable();

            $table->unsignedSmallInteger('start_year')->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();

            $table->string('degree', 100)->nullable();

            $table->timestamps();

            $table->index('gtk_id');
            $table->index('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gtk_educations');
    }
};
