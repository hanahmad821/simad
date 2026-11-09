<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{Gtk,User,AcademicYear,Semester,SchoolClass,Subject,TeachingAssignment,Schedule,LessonPeriod,Student,StudentClassHistory,IncomingLetter,LetterDisposition};
Illuminate\Support\Facades\DB::transaction(function () {
 $gtk=Gtk::firstOrCreate(['email'=>'guru.panduan@example.com'],['full_name'=>'Guru Contoh MADIKA','gender'=>'male','birth_place'=>'Banyumas','birth_date'=>'1990-01-15','employment_status'=>'non_pns','position'=>'Guru','address'=>'Alamat contoh untuk panduan','is_active'=>true]);
 $user=User::firstOrCreate(['email'=>'guru.panduan@example.com'],['name'=>$gtk->full_name,'password'=>'PanduanGuru2026!','role'=>'teacher','gtk_id'=>$gtk->id]);
 $user->forceFill(['email_verified_at'=>now()])->save();
 $gtk->educations()->firstOrCreate(['institution_name'=>'Universitas Contoh'],['level'=>'S1','major'=>'Pendidikan Bahasa Indonesia','start_year'=>2008,'graduation_year'=>2012,'degree'=>'S.Pd.']);
 $semester=Semester::where('is_active',true)->firstOrFail();
 $class=SchoolClass::firstOrCreate(['name'=>'X Panduan'],['grade'=>10,'capacity'=>32,'is_active'=>true]);
 $subject=Subject::where('name','like','%Indonesia%')->firstOrFail();
 $assignment=TeachingAssignment::firstOrCreate(['gtk_id'=>$gtk->id,'school_class_id'=>$class->id,'subject_id'=>$subject->id,'semester_id'=>$semester->id,'academic_year_id'=>$semester->academic_year_id],['teaching_hours'=>2,'is_active'=>true]);
 $room=App\Models\Room::firstOrCreate(['code'=>'DEMO-SOP'],['name'=>'Ruang Contoh','is_active'=>true]);
 foreach(LessonPeriod::whereIn('period_number',[1,2])->where('is_active',true)->get() as $period){Schedule::firstOrCreate(['teaching_assignment_id'=>$assignment->id,'lesson_period_id'=>$period->id,'day_of_week'=>now()->dayOfWeekIso],['room_id'=>$room->id,'is_active'=>true]);}
 foreach(['Alya Contoh','Bima Contoh','Citra Contoh'] as $i=>$name){
  $student=Student::firstOrCreate(['student_code'=>'PANDUAN-'.($i+1)],['full_name'=>$name,'nis'=>'DEMO00'.($i+1),'gender'=>$i===1?'male':'female','is_active'=>true]);
  StudentClassHistory::firstOrCreate(['student_id'=>$student->id,'school_class_id'=>$class->id,'semester_id'=>$semester->id,'academic_year_id'=>$semester->academic_year_id],['is_active'=>true]);
 }
 $sender=User::firstOrCreate(['email'=>'kepala.panduan@example.com'],['name'=>'Kepala Madrasah Contoh','password'=>Illuminate\Support\Str::random(40),'role'=>'headmaster']);
 $letter=IncomingLetter::firstOrCreate(['nomor_surat'=>'DEMO/SOP/001/2026'],['pengirim'=>'Panitia Kegiatan Contoh','tanggal_surat'=>today(),'tanggal_masuk'=>today(),'perihal'=>'Koordinasi kegiatan literasi contoh','keterangan'=>'Data dummy untuk panduan guru']);
 LetterDisposition::firstOrCreate(['incoming_letter_id'=>$letter->id,'to_user_id'=>$user->id],['from_user_id'=>$sender->id,'instruction'=>'Siapkan rencana kegiatan literasi kelas dan laporkan hasil koordinasi.','deadline'=>today()->addDays(7),'status'=>'pending']);
 file_put_contents(__DIR__.'/demo-ids.json',json_encode(['user'=>$user->id,'gtk'=>$gtk->id,'class'=>$class->id,'assignment'=>$assignment->id,'letter'=>$letter->id],JSON_PRETTY_PRINT));
 echo "Akun dan data dummy panduan guru siap.\n";
});
