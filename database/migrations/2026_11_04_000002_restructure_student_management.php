<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->restrictOnDelete();
            $table->boolean('historical_metadata_unknown')->default(false);
            if (Schema::hasIndex('school_classes', 'school_classes_name_unique')) $table->dropUnique('school_classes_name_unique');
            $table->unique(['academic_year_id', 'name']);
        });
        Schema::table('student_class_histories', function (Blueprint $table) {
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->dropUnique('sch_unique_student_period');
        });
        Schema::create('student_management_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 30);
            $table->json('request');
            $table->timestamps();
        });
        Schema::create('student_management_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('batch_id')->constrained('student_management_batches')->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action', 30);
            $table->text('reason')->nullable();
            $table->json('before');
            $table->json('after');
            $table->unsignedBigInteger('corrects_event_id')->nullable()->unique();
            $table->timestamps();
        });
        Schema::create('student_year_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_history_id')->constrained('student_class_histories')->restrictOnDelete();
            $table->foreignId('destination_history_id')->nullable()->constrained('student_class_histories')->restrictOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('student_management_events')->restrictOnDelete();
            $table->string('action', 20);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year_id']);
        });
        Schema::create('student_data_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('description');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
        Schema::table('students', function (Blueprint $table) {
            $table->text('exit_reason')->nullable();
            $table->string('destination_school')->nullable();
        });

        $activeYear = DB::table('academic_years')->where('is_active', true)->value('id');
        foreach (DB::table('school_classes')->orderBy('id')->get() as $class) {
            $years = DB::table('student_class_histories')->where('school_class_id', $class->id)->pluck('academic_year_id')
                ->merge(DB::table('teaching_assignments')->where('school_class_id', $class->id)->pluck('academic_year_id'))
                ->unique()->values();
            if ($years->isEmpty() && $activeYear) $years->push($activeYear);
            $primary = $years->contains($activeYear) ? $activeYear : DB::table('academic_years')->whereIn('id', $years)->orderByDesc('start_date')->value('id');
            DB::table('school_classes')->where('id', $class->id)->update([
                'academic_year_id' => $primary,
                'historical_metadata_unknown' => $primary && $primary != $activeYear,
                'homeroom_teacher_id' => $primary == $activeYear ? $class->homeroom_teacher_id : null,
                'room_id' => $primary == $activeYear ? $class->room_id : null,
                'capacity' => $primary == $activeYear ? $class->capacity : null,
            ]);
            foreach ($years as $yearId) {
                if ($yearId == $primary) continue;
                $copy = (array) $class;
                unset($copy['id']);
                $copy['academic_year_id'] = $yearId;
                $copy['homeroom_teacher_id'] = null;
                $copy['room_id'] = null;
                $copy['capacity'] = null;
                $copy['historical_metadata_unknown'] = true;
                $newId = DB::table('school_classes')->insertGetId($copy);
                foreach (['student_class_histories', 'teaching_assignments'] as $table) {
                    DB::table($table)->where('school_class_id', $class->id)->where('academic_year_id', $yearId)
                        ->update(['school_class_id' => $newId]);
                }
            }
        }

        $groups = DB::table('student_class_histories')->select('student_id', 'academic_year_id')
            ->groupBy('student_id', 'academic_year_id')->get();
        foreach ($groups as $group) {
            $histories = DB::table('student_class_histories')->where('student_id', $group->student_id)
                ->where('academic_year_id', $group->academic_year_id)->get();
            if ($histories->count() > 1) {
                DB::table('student_data_issues')->insert([
                    'student_id' => $group->student_id, 'academic_year_id' => $group->academic_year_id,
                    'description' => 'Beberapa riwayat kelas lama belum memiliki tanggal penempatan yang pasti. Periksa interval sebelum memproses siswa.',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                continue;
            }
            $year = DB::table('academic_years')->find($group->academic_year_id);
            $student = DB::table('students')->find($group->student_id);
            $end = $student->exit_academic_year_id == $year->id && $student->exit_date ? min($student->exit_date, $year->end_date) : $year->end_date;
            DB::table('student_class_histories')->where('id', $histories->first()->id)->update(['starts_on' => $year->start_date, 'ends_on' => $end]);
        }

        foreach (DB::table('student_progressions')->orderBy('id')->get() as $old) {
            $history = DB::table('student_class_histories')->find($old->source_history_id);
            $batch = (string) Str::uuid();
            DB::table('student_management_batches')->insert([
                'id' => $batch, 'actor_id' => $old->processed_by, 'kind' => 'legacy',
                'request' => json_encode(['legacy_progression_id' => $old->id]),
                'created_at' => $old->created_at, 'updated_at' => $old->updated_at,
            ]);
            $event = DB::table('student_management_events')->insertGetId([
                'batch_id' => $batch, 'student_id' => $old->student_id, 'academic_year_id' => $history->academic_year_id,
                'action' => $old->action, 'reason' => 'Diimpor dari proses sebelumnya; snapshot sebelum perubahan tidak tersedia.',
                'before' => '{}', 'after' => json_encode((array) $old), 'created_at' => $old->created_at, 'updated_at' => $old->updated_at,
            ]);
            if (! in_array($old->action, ['promote', 'repeat', 'graduate'])) continue;
            if (DB::table('student_year_decisions')->where('student_id', $old->student_id)->where('academic_year_id', $history->academic_year_id)->exists()) {
                DB::table('student_data_issues')->insert([
                    'student_id' => $old->student_id, 'academic_year_id' => $history->academic_year_id,
                    'description' => 'Keputusan akhir tahun lama ganda. Semua audit dipertahankan; perlu rekonsiliasi.',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                continue;
            }
            DB::table('student_year_decisions')->insert([
                'student_id' => $old->student_id, 'academic_year_id' => $history->academic_year_id,
                'source_history_id' => $old->source_history_id, 'destination_history_id' => $old->destination_history_id,
                'action' => $old->action, 'event_id' => $event, 'created_at' => $old->created_at, 'updated_at' => $old->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Rombel tahunan dan audit siswa tidak dapat digabung kembali otomatis. Gunakan cadangan sebelum migrasi.');
    }
};
