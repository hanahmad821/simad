<?php

namespace App\Http\Controllers;

use App\Models\Gtk;
use App\Models\GtkAttendance;
use App\Models\IncomingLetter;
use App\Models\OutgoingLetter;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $role = $user->activeRole();
        $title = match ($role) {
            'admin' => 'Dashboard Administrator',
            'headmaster' => 'Dashboard Kepala Madrasah',
            'teacher' => 'Dashboard Guru',
            'staff' => 'Dashboard Staf',
            default => abort(403),
        };
        $tasks = $user->receivedDispositions()->whereIn('status', ['pending', 'read', 'in_progress']);
        $stats = [
            ['label' => 'Disposisi aktif', 'value' => (clone $tasks)->count()],
            ['label' => 'Belum dibaca', 'value' => $user->receivedDispositions()->where('status', 'pending')->count()],
            ['label' => 'Lewat tenggat', 'value' => (clone $tasks)->whereDate('deadline', '<', today())->count()],
            ['label' => 'Selesai', 'value' => $user->receivedDispositions()->where('status', 'completed')->count()],
        ];
        $dispositions = (clone $tasks)->with('incomingLetter')
            ->orderByRaw('deadline IS NULL')->orderBy('deadline')->latest('id')->limit(5)->get();
        $attendance = $user->gtk_id ? GtkAttendance::where('gtk_id', $user->gtk_id)
            ->whereDate('attendance_date', today())->first() : null;
        $schedules = collect();
        $letters = collect();
        $links = [
            ['label' => 'Presensi Saya', 'route' => 'gtk.attendance.index'],
            ['label' => 'Riwayat Presensi', 'route' => 'gtk.attendance.history'],
            ['label' => 'Disposisi Saya', 'route' => 'gtk.dispositions.index'],
        ];

        if (in_array($role, ['admin', 'headmaster'], true)) {
            $stats = [
                ['label' => 'GTK aktif', 'value' => Gtk::where('is_active', true)->count()],
                ['label' => 'Siswa aktif', 'value' => Student::where('is_active', true)->count()],
                ['label' => 'Kelas aktif', 'value' => SchoolClass::where('is_active', true)->count()],
                ['label' => 'GTK hadir hari ini', 'value' => GtkAttendance::whereDate('attendance_date', today())->whereNotNull('check_in_at')->count()],
                ['label' => 'Surat masuk bulan ini', 'value' => IncomingLetter::whereBetween('tanggal_masuk', [today()->startOfMonth(), today()->endOfMonth()])->count()],
                ['label' => 'Menunggu persetujuan', 'value' => OutgoingLetter::where('status', 'pending')->count()],
            ];
            $letters = OutgoingLetter::where('status', 'pending')->oldest()->limit(5)->get();
            $links = array_merge([
                ['label' => 'Surat Masuk', 'route' => $role.'.incoming-letters.index'],
                ['label' => 'Surat Keluar', 'route' => $role.'.outgoing-letters.index'],
            ], $links);
            if ($role === 'admin') {
                $links = array_merge([
                    ['label' => 'Kelola GTK', 'route' => 'admin.gtks.index'],
                    ['label' => 'Kelola Siswa', 'route' => 'admin.students.index'],
                    ['label' => 'Kelola Ruangan', 'route' => 'admin.rooms.index'],
                    ['label' => 'Jadwal Pelajaran', 'route' => 'admin.schedules.index'],
                    ['label' => 'Pengaturan Waktu KBM', 'route' => 'admin.kbm-times.index'],
                    ['label' => 'Rekap Presensi GTK', 'route' => 'admin.gtk-attendances.index'],
                ], $links);
            }
        }

        if ($user->isStaff()) {
            $links = array_merge([
                ['label' => 'Presensi GTK', 'route' => 'staff.gtk-attendances.index'],
                ['label' => 'Presensi Siswa', 'route' => 'staff.student-attendances.index'],
                ['label' => 'Surat Masuk', 'route' => 'staff.incoming-letters.index'],
                ['label' => 'Surat Keluar', 'route' => 'staff.outgoing-letters.index'],
            ], $links);
        }

        if ($user->canTeach() && $user->gtk_id) {
            $schedules = Schedule::with(['lessonPeriod', 'room', 'teachingAssignment.subject', 'teachingAssignment.schoolClass'])
                ->where('is_active', true)->where('day_of_week', today()->isoWeekday())
                ->whereHas('teachingAssignment', fn ($query) => $query->where('gtk_id', $user->gtk_id)
                    ->where('is_active', true)->whereHas('academicYear', fn ($year) => $year->where('is_active', true)))
                ->get()->filter(fn ($schedule) => $schedule->lessonPeriod && ($schedule->lessonPeriod->day_of_week === null
                    || ($schedule->lessonPeriod->day_of_week === $schedule->day_of_week && $schedule->lessonPeriod->is_active
                        && ! $schedule->lessonPeriod->is_break && $schedule->lessonPeriod->period_number > 0)))
                ->sortBy(fn ($schedule) => $schedule->lessonPeriod->start_time)->values();
            array_unshift($stats, ['label' => 'Jadwal hari ini', 'value' => $schedules->count()]);
        }

        if ($user->canTeach()) {
            array_unshift($links, ['label' => 'Jadwal Mengajar', 'route' => 'teacher.schedules.index']);
            array_unshift($links, ['label' => 'Presensi Siswa', 'route' => 'teacher.student-attendances.index']);
        }

        $links[] = ['label' => 'Riwayat Presensi Siswa', 'route' => $role.'.student-attendances.history'];

        return view('dashboards.index', compact('title', 'role', 'stats', 'links', 'attendance', 'dispositions', 'schedules', 'letters'));
    }
}
