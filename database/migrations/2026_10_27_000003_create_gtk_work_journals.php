<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gtk_work_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gtk_id')->constrained('gtks')->restrictOnDelete();
            $table->date('journal_date');
            $table->string('session_key', 60);
            $table->string('kind', 20);
            $table->string('label');
            $table->json('schedule_ids')->nullable();
            $table->text('material')->nullable();
            $table->text('notes')->nullable();
            $table->text('reflection')->nullable();
            $table->text('activities')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['gtk_id', 'journal_date', 'session_key'], 'gtk_journal_session_unique');
        });
        Schema::create('gtk_monthly_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gtk_id')->constrained('gtks')->restrictOnDelete();
            $table->date('month');
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['gtk_id', 'month']);
        });
        Schema::create('gtk_kbm_exceptions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('schedule_id');
            $table->foreignId('gtk_id')->constrained('gtks')->restrictOnDelete();
            $table->foreignId('substitute_gtk_id')->nullable()->constrained('gtks')->restrictOnDelete();
            $table->string('label');
            $table->json('schedule_ids');
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['date', 'schedule_id']);
        });
        Schema::create('gtk_checkout_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gtk_id')->constrained('gtks')->restrictOnDelete();
            $table->date('date');
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['gtk_id', 'date']);
        });
        Schema::create('gtk_work_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gtk_id')->constrained('gtks')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->date('date');
            $table->string('event');
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gtk_work_audits');
        Schema::dropIfExists('gtk_checkout_exceptions');
        Schema::dropIfExists('gtk_kbm_exceptions');
        Schema::dropIfExists('gtk_monthly_reviews');
        Schema::dropIfExists('gtk_work_journals');
    }
};
