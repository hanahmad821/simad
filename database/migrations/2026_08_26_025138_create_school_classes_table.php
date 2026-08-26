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
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();

            $table->string('name', 50);
            $table->unsignedTinyInteger('grade');
            $table->string('major', 100)->nullable();

            $table->unsignedSmallInteger('capacity')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('grade');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
