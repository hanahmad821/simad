<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Older databases may already contain these columns from the base migration.
        if (! Schema::hasColumn('outgoing_letters', 'letter_type_id')) {
            Schema::table('outgoing_letters', fn (Blueprint $table) => $table->unsignedBigInteger('letter_type_id')->nullable());
        }
        if (! Schema::hasColumn('outgoing_letters', 'nomor_urut')) {
            Schema::table('outgoing_letters', fn (Blueprint $table) => $table->unsignedInteger('nomor_urut')->nullable());
        }
        if (! Schema::hasColumn('outgoing_letters', 'nomor_tahun')) {
            Schema::table('outgoing_letters', fn (Blueprint $table) => $table->unsignedSmallInteger('nomor_tahun')->nullable());
        }
        if (! Schema::hasIndex('outgoing_letters', ['nomor_urut', 'nomor_tahun'], 'unique')) {
            Schema::table('outgoing_letters', fn (Blueprint $table) => $table->unique(['nomor_urut', 'nomor_tahun'], 'ol_unique_number_year'));
        }
        foreach (['letter_type_id', 'nomor_tahun'] as $column) {
            if (! Schema::hasIndex('outgoing_letters', [$column])) {
                Schema::table('outgoing_letters', fn (Blueprint $table) => $table->index($column));
            }
        }
        // letter_types is created after the original outgoing_letters table.
        if (! Schema::hasForeignKey('outgoing_letters', ['letter_type_id'])) {
            Schema::table('outgoing_letters', fn (Blueprint $table) => $table->foreign('letter_type_id')->references('id')->on('letter_types')->restrictOnDelete());
        }
    }

    public function down(): void
    {
        // Keep the numbering columns: they also belong to the base table and hold archive data.
        if (Schema::hasForeignKey('outgoing_letters', 'outgoing_letters_letter_type_id_foreign')) {
            Schema::table('outgoing_letters', fn (Blueprint $table) => $table->dropForeign(['letter_type_id']));
        }
    }
};
