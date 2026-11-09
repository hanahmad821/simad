<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gtk_absence_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gtk_id')->constrained('gtks')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 3);
            $table->string('description', 1000);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('evidence_path');
            $table->string('status', 16)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['gtk_id', 'status', 'start_date', 'end_date'], 'absence_gtk_range');
            $table->index(['status', 'created_at']);
        });
        Schema::table('gtk_attendances', function (Blueprint $table) {
            $table->foreignId('gtk_absence_request_id')->nullable()->constrained('gtk_absence_requests')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gtk_attendances', fn (Blueprint $table) => $table->dropConstrainedForeignId('gtk_absence_request_id'));
        Schema::dropIfExists('gtk_absence_requests');
    }
};
