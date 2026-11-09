<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gtk_daily_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gtk_id')->constrained('gtks')->restrictOnDelete();
            $table->date('date');
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['gtk_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gtk_daily_approvals');
    }
};
