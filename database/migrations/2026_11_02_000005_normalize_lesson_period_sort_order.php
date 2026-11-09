<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('lesson_periods', 'sort_order')) {
            Schema::table('lesson_periods', function (Blueprint $table) {
                $table->unsignedTinyInteger('sort_order')->nullable();
            });
        }
        DB::table('lesson_periods')->whereNotNull('slot_order')->update(['sort_order' => DB::raw('slot_order')]);
        Schema::table('lesson_periods', function (Blueprint $table) {
            $table->dropIndex('lesson_periods_day_order_index');
            $table->dropColumn('slot_order');
        });
        Schema::table('lesson_periods', function (Blueprint $table) {
            $table->index(['day_of_week', 'sort_order'], 'lesson_periods_day_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_periods', function (Blueprint $table) {
            $table->dropIndex('lesson_periods_day_order_index');
            $table->unsignedTinyInteger('slot_order')->nullable();
        });
        DB::table('lesson_periods')->update(['slot_order' => DB::raw('sort_order')]);
        Schema::table('lesson_periods', function (Blueprint $table) {
            $table->index(['day_of_week', 'slot_order'], 'lesson_periods_day_order_index');
        });
    }
};
