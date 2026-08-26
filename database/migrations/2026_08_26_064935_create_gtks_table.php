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
        Schema::create('gtks', function (Blueprint $table) {
            $table->id();

            // Identitas utama
            $table->string('full_name', 150);
            $table->string('nip', 30)->nullable()->unique();
            $table->string('nuptk', 30)->nullable()->unique();

            // Identitas pribadi
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();

            // Kepegawaian
            $table->enum('employment_status', [
                'pns',
                'non_pns',
                'yayasan',
            ])->default('non_pns');

            $table->string('employee_number', 50)->nullable();
            $table->string('position', 100)->nullable();

            // Kontak
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();

            // Alamat
            $table->text('address')->nullable();

            // Status data
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('full_name');
            $table->index('employment_status');
            $table->index('position');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gtks');
    }
};
