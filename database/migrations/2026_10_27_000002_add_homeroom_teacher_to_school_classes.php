<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('school_classes', function (Blueprint $table) { $table->foreignId('homeroom_teacher_id')->nullable()->constrained('gtks')->nullOnDelete(); }); }
    public function down(): void { Schema::table('school_classes', function (Blueprint $table) { $table->dropConstrainedForeignId('homeroom_teacher_id'); }); }
};
