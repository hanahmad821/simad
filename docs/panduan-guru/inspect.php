<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode([
 'date'=>now()->toDateTimeString(),
 'year'=>App\Models\AcademicYear::where('is_active',true)->first()?->only(['id','name']),
 'semester'=>App\Models\Semester::where('is_active',true)->first()?->only(['id','academic_year_id','name','start_date','end_date']),
 'periods'=>App\Models\LessonPeriod::where('is_active',true)->where('is_break',false)->limit(3)->get(['id','period_number','start_time','end_time']),
 'tables'=>collect(['gtks','gtk_educations','students','incoming_letters','letter_dispositions','rooms'])->mapWithKeys(fn($t)=>[$t=>Illuminate\Support\Facades\Schema::getColumns($t)])->all(),
],JSON_PRETTY_PRINT);
