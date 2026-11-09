<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gtk_attendances', function (Blueprint $table) {
            $table->timestamp('scheduled_check_in_at')->nullable();
            $table->timestamp('scheduled_check_out_at')->nullable();
            $table->timestamp('forgotten_checkout_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('gtk_attendances', function (Blueprint $table) {
            $table->dropColumn(['scheduled_check_in_at', 'scheduled_check_out_at', 'forgotten_checkout_at']);
        });
    }
};
