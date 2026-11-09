<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some local installations acquired these columns outside migration history.
        // Check each column independently to support fresh, imported and partial schemas.
        if (! Schema::hasColumn('outgoing_letters', 'jenis_surat')) {
            Schema::table('outgoing_letters', fn (Blueprint $table) => $table->string('jenis_surat')->nullable());
            DB::table('outgoing_letters')->whereNotNull('letter_type_id')->orderBy('id')->chunkById(200, function ($letters) {
                $names = DB::table('letter_types')->whereIn('id', $letters->pluck('letter_type_id'))->pluck('name', 'id');
                foreach ($letters as $letter) {
                    $name = $names->get($letter->letter_type_id);
                    if ($name !== null) {
                        DB::table('outgoing_letters')->where('id', $letter->id)->update(['jenis_surat' => $name]);
                    }
                }
            });
        }
        $jenis = collect(Schema::getColumns('outgoing_letters'))->firstWhere('name', 'jenis_surat');
        if ($jenis['nullable']) {
            DB::table('outgoing_letters')->whereNull('jenis_surat')->update(['jenis_surat' => 'Surat Keluar']);
            Schema::table('outgoing_letters', fn (Blueprint $table) => $table->string('jenis_surat')->change());
        }
        if (! Schema::hasColumn('outgoing_letters', 'use_template')) {
            Schema::table('outgoing_letters', fn (Blueprint $table) => $table->boolean('use_template')->default(true));
            // An existing uploaded source document identifies the legacy manual workflow.
            DB::table('outgoing_letters')->whereNotNull('file')->where('file', '<>', '')->update(['use_template' => false]);
        }
        foreach (['final_docx_file', 'final_pdf_file'] as $column) {
            if (! Schema::hasColumn('outgoing_letters', $column)) {
                Schema::table('outgoing_letters', fn (Blueprint $table) => $table->string($column)->nullable());
            }
        }
        // Copy only legacy archive references; never regenerate or rename their files.
        DB::table('outgoing_letters')->whereNotNull('final_file')->where(function ($query) {
            $query->whereNull('final_docx_file')->orWhereNull('final_pdf_file');
        })->orderBy('id')->chunkById(200, function ($letters) {
            foreach ($letters as $letter) {
                $extension = strtolower(pathinfo($letter->final_file, PATHINFO_EXTENSION));
                $column = match ($extension) {
                    'pdf' => 'final_pdf_file', 'docx' => 'final_docx_file', default => null
                };
                if ($column !== null && $letter->$column === null) {
                    DB::table('outgoing_letters')->where('id', $letter->id)->update([$column => $letter->final_file]);
                }
            }
        });

        // Match the daily-KBM schema used by the application, retaining existing order values.
        $period = collect(Schema::getColumns('lesson_periods'))->firstWhere('name', 'period_number');
        if (! $period['nullable']) {
            Schema::table('lesson_periods', fn (Blueprint $table) => $table->unsignedTinyInteger('period_number')->nullable()->change());
        }
        $sort = collect(Schema::getColumns('lesson_periods'))->firstWhere('name', 'sort_order');
        if ($sort['nullable'] || $sort['type'] !== 'tinyint') {
            $updates = [];
            foreach (DB::table('lesson_periods')->orderBy('day_of_week')->orderBy('start_time')->orderBy('id')->get()->groupBy('day_of_week') as $rows) {
                foreach ($rows->values() as $index => $row) {
                    $value = $row->sort_order ?? $index;
                    if ($value < -128 || $value > 127) {
                        throw new RuntimeException('Urutan jam pelajaran di luar rentang. Periksa sort_order sebelum melanjutkan migration.');
                    }
                    if ($row->sort_order === null) {
                        $updates[$row->id] = $value;
                    }
                }
            }
            foreach ($updates as $id => $value) {
                DB::table('lesson_periods')->where('id', $id)->update(['sort_order' => $value]);
            }
            Schema::table('lesson_periods', fn (Blueprint $table) => $table->tinyInteger('sort_order')->change());
        }

        // One subject/class/year/semester assignment must not be assigned to two GTK.
        $identity = ['subject_id', 'school_class_id', 'academic_year_id', 'semester_id'];
        if (! Schema::hasIndex('teaching_assignments', $identity, 'unique')) {
            if (DB::table('teaching_assignments')->select($identity)->whereNotNull('semester_id')->groupBy($identity)->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException('Ada penugasan ganda untuk mata pelajaran, kelas, tahun, dan semester yang sama. Rapikan penugasan sebelum melanjutkan migration; data tidak dihapus otomatis.');
            }
            if (! Schema::hasIndex('teaching_assignments', ['gtk_id'])) {
                Schema::table('teaching_assignments', fn (Blueprint $table) => $table->index('gtk_id'));
            }
            Schema::table('teaching_assignments', function (Blueprint $table) use ($identity) {
                if (Schema::hasIndex('teaching_assignments', 'ta_unique_assignment')) {
                    $table->dropUnique('ta_unique_assignment');
                }
                $table->unique($identity, 'ta_unique_assignment');
            });
        }
    }

    public function down(): void
    {
        // These fields can predate this migration and may already identify signed archives.
        throw new RuntimeException('Skema dan arsip lama tidak dihapus otomatis. Gunakan cadangan untuk mengembalikan skema sebelumnya.');
    }
};
