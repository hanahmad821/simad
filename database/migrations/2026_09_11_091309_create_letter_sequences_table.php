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
        Schema::create('letter_sequences', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('year');

            $table->unsignedInteger('last_number')->default(0);

            $table->timestamps();

            $table->unique('year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_sequences');
    }
};
