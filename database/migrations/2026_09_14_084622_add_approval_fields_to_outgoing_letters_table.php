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
            $table->foreignId('approved_by')
                ->nullable()
                ->after('created_by')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')
                ->nullable()
                ->after('approved_by');

            $table->text('rejection_reason')
                ->nullable()
                ->after('approved_at');

            $table->index('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table) {
            //
        });
    }
};
