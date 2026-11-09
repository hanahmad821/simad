<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_types', function (Blueprint $table) {
            $table->longText('body_html')->nullable();
            $table->unsignedInteger('template_version')->default(1);
        });
        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->json('template_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_letters', fn (Blueprint $table) => $table->dropColumn('template_snapshot'));
        Schema::table('letter_types', fn (Blueprint $table) => $table->dropColumn(['body_html', 'template_version']));
    }
};
