<?php

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

it('annualizes legacy classes without losing decisions and flags ambiguous dates', function () {
    if (DB::connection()->getDatabaseName() !== ':memory:') throw new RuntimeException('Isolated database required');
    foreach ([
        '0001_01_01_000000_create_users_table.php','2025_08_14_170933_add_two_factor_columns_to_users_table.php',
        '2026_08_26_064935_create_gtks_table.php','2026_09_08_042246_add_role_and_gtk_id_to_users_table.php', '2026_11_05_000001_add_secondary_role_to_users.php',
        '2026_08_26_020757_create_academic_years_table.php','2026_08_26_021543_create_semesters_table.php',
        '2026_08_26_025138_create_school_classes_table.php','2026_08_26_025918_create_subjects_table.php',
        '2026_08_26_064539_create_rooms_table.php','2026_08_26_071443_create_teaching_assignments_table.php',
        '2026_08_26_074302_create_students_table.php','2026_08_26_075134_create_student_class_histories_table.php',
        '2026_10_27_000002_add_homeroom_teacher_to_school_classes.php','2026_11_02_000002_make_kbm_independent_of_semesters.php',
        '2026_11_02_000003_add_room_to_school_classes.php','2026_11_04_000001_add_student_progression.php',
    ] as $migration) (require database_path('migrations/'.$migration))->up();
    $actor = User::factory()->create(['role'=>'admin']);
    $old = AcademicYear::create(['name'=>'2025/2026','start_date'=>'2025-07-01','end_date'=>'2026-06-30']);
    $current = AcademicYear::create(['name'=>'2026/2027','start_date'=>'2026-07-01','end_date'=>'2027-06-30','is_active'=>true]);
    $class = SchoolClass::create(['name'=>'X A','grade'=>10,'capacity'=>30]);
    $student = Student::create(['student_code'=>'OLD','full_name'=>'Siswa Lama']);
    $source = StudentClassHistory::create(['student_id'=>$student->id,'school_class_id'=>$class->id,'academic_year_id'=>$old->id,'is_active'=>false]);
    $target = StudentClassHistory::create(['student_id'=>$student->id,'school_class_id'=>$class->id,'academic_year_id'=>$current->id]);
    DB::table('student_progressions')->insert(['student_id'=>$student->id,'source_history_id'=>$source->id,'destination_history_id'=>$target->id,'processed_by'=>$actor->id,'action'=>'repeat','effective_date'=>'2026-06-30','created_at'=>now(),'updated_at'=>now()]);
    $ambiguous = Student::create(['student_code'=>'AMB','full_name'=>'Perlu diperiksa']);
    foreach ([true,false] as $active) StudentClassHistory::create(['student_id'=>$ambiguous->id,'school_class_id'=>$class->id,'academic_year_id'=>$current->id,'is_active'=>$active]);
    (require database_path('migrations/2026_11_04_000002_restructure_student_management.php'))->up();
    expect(SchoolClass::count())->toBe(2)->and($class->fresh()->academic_year_id)->toBe($current->id)
        ->and($source->fresh()->school_class_id)->not->toBe($class->id)
        ->and($source->fresh()->schoolClass->capacity)->toBeNull()
        ->and($source->fresh()->schoolClass->historical_metadata_unknown)->toBeTrue()
        ->and($source->fresh()->starts_on->toDateString())->toBe('2025-07-01')
        ->and(DB::table('student_year_decisions')->value('action'))->toBe('repeat')
        ->and(DB::table('student_management_events')->count())->toBe(1)
        ->and(DB::table('student_progressions')->count())->toBe(1)
        ->and(DB::table('student_data_issues')->value('student_id'))->toBe($ambiguous->id)
        ->and($ambiguous->classHistories()->whereNull('starts_on')->count())->toBe(2);
});
