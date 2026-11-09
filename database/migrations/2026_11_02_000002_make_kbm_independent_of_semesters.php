<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['teaching_assignments', 'student_class_histories'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                // Retain legacy references for history; new KBM data needs only a school year.
                $table->unsignedBigInteger('semester_id')->nullable()->change();
            });
        }

        // Carry forward one active version per school year without deleting historical rows,
        // schedules, attendance anchors or journal snapshots from the previous semesters.
        foreach ([
            'teaching_assignments' => ['gtk_id', 'subject_id', 'school_class_id', 'academic_year_id'],
            'student_class_histories' => ['student_id', 'academic_year_id'],
        ] as $name => $identity) {
            $duplicates = DB::table($name)->select($identity)->where('is_active', true)
                ->groupBy($identity)->havingRaw('COUNT(*) > 1')->get();
            foreach ($duplicates as $duplicate) {
                $query = DB::table($name)->leftJoin('semesters', 'semesters.id', '=', $name.'.semester_id')
                    ->where($name.'.is_active', true);
                foreach ($identity as $column) {
                    $query->where($name.'.'.$column, $duplicate->$column);
                }
                // Prefer the previously active semester, then the most recent version.
                $ids = $query->orderByDesc('semesters.is_active')->orderByDesc('semesters.start_date')
                    ->orderByDesc($name.'.id')->pluck($name.'.id');
                DB::table($name)->whereIn('id', $ids->slice(1)->all())->update(['is_active' => false]);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('KBM tanpa semester tidak dapat dikembalikan otomatis. Pulihkan cadangan sebelum migrasi untuk mengembalikan pembagian semester lama.');
    }
};
