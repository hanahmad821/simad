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
        Schema::table('school_settings', function (Blueprint $table) {
            $table->string('headmaster_name')->nullable()->after('email');
            $table->string('headmaster_nip')->nullable()->after('headmaster_name');
            $table->string('headmaster_rank')->nullable()->after('headmaster_nip');
            $table->string('headmaster_position')->nullable()->after('headmaster_rank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            //
        });
    }
};
