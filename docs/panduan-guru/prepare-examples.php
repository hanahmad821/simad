<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$ids=json_decode(file_get_contents(__DIR__.'/demo-ids.json'),true);
$user=App\Models\User::findOrFail($ids['user']);
$session=app(App\Services\GtkJournalService::class)->sessions($ids['gtk'],today())->first();
$students=App\Models\Student::where('student_code','like','PANDUAN-%')->orderBy('student_code')->get();
foreach($students as $i=>$student){App\Models\StudentAttendance::updateOrCreate(['student_id'=>$student->id,'schedule_id'=>$session['schedule_ids'][0],'attendance_date'=>today()->toDateString()],['status'=>$i===2?'sick':'present','notes'=>$i===2?'Contoh izin sakit dari orang tua.':null,'recorded_by'=>$user->id]);}
App\Models\GtkAttendance::firstOrCreate(['gtk_id'=>$ids['gtk'],'attendance_date'=>today()->toDateString()],['check_in_at'=>today()->setTime(7,0),'status'=>'present','notes'=>'Data contoh panduan']);
echo "Contoh presensi siswa dan check in dummy disiapkan.\n";
