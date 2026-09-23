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
        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->foreignId('letter_type_id')
                ->nullable()
                ->after('id')
                ->constrained('letter_types')
                ->restrictOnDelete();

            $table->unsignedInteger('nomor_urut')
                ->nullable()
                ->after('letter_type_id');

            $table->unsignedSmallInteger('nomor_tahun')
                ->nullable()
                ->after('nomor_urut');

            $table->unique(
                ['nomor_urut', 'nomor_tahun'],
                'ol_unique_number_year'
            );

            $table->index('letter_type_id');
            $table->index('nomor_tahun');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->dropUnique('ol_unique_number_year');

            $table->dropForeign(['letter_type_id']);
            $table->dropIndex(['letter_type_id']);
            $table->dropIndex(['nomor_tahun']);

            $table->dropColumn([
                'letter_type_id',
                'nomor_urut',
                'nomor_tahun',
            ]);
        });
    }
};
