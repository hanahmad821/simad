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
    Schema::create('letter_dispositions', function (Blueprint $table) {
        $table->id();

        $table->foreignId('incoming_letter_id')
            ->constrained('incoming_letters')
            ->cascadeOnDelete();

        // User yang membuat disposisi
        $table->foreignId('from_user_id')
            ->constrained('users')
            ->restrictOnDelete();

        // User penerima disposisi
        $table->foreignId('to_user_id')
            ->constrained('users')
            ->restrictOnDelete();

        $table->text('instruction');

        $table->date('deadline')->nullable();

        $table->enum('status', [
            'pending',
            'read',
            'in_progress',
            'completed',
        ])->default('pending');

        $table->timestamp('read_at')->nullable();
        $table->timestamp('completed_at')->nullable();

        $table->text('notes')->nullable();

        $table->timestamps();

        $table->index(['to_user_id', 'status']);
        $table->index(['incoming_letter_id', 'status']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letter_dispositions');
    }
};
