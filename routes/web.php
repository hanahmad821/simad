<?php

use App\Http\Controllers\Admin\KbmTimeController;
use App\Http\Controllers\Admin\LetterTypeController;
use App\Http\Controllers\Admin\OutgoingLetterController;
use App\Http\Controllers\Admin\OutgoingLetterPreviewController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\SchoolClassController;
use App\Http\Controllers\Admin\SchoolProfileController;
use App\Http\Controllers\Admin\StudentCardController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GtkAbsenceController;
use App\Http\Controllers\GtkJournalController;
use App\Http\Controllers\GtkJournalHistoryController;
use App\Http\Controllers\GtkWorkReviewController;
use App\Http\Controllers\LetterVerificationController;
use App\Http\Controllers\OutgoingLetterFinalPdfController;
use App\Http\Controllers\SwitchRoleController;
use App\Models\Gtk;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::post('/switch-role', SwitchRoleController::class)->name('role.switch');

    Route::get('/outgoing-letters/{outgoingLetter}/attachments/{attachment}', \App\Http\Controllers\OutgoingLetterAttachmentController::class)
        ->middleware('role:admin,staff,headmaster')->scopeBindings()->name('outgoing-letters.attachments.download');

    Route::get('/outgoing-letters/{outgoingLetter}/final-pdf', OutgoingLetterFinalPdfController::class)
        ->middleware('role:admin,staff,headmaster')
        ->name('outgoing-letters.final-pdf');
    Route::get('/outgoing-letters/{outgoingLetter}/preview', OutgoingLetterPreviewController::class)
        ->middleware('role:admin,staff,headmaster')->name('outgoing-letters.preview');

    // Pengajuan ketidakhadiran GTK dan bukti privat.
    Route::middleware('role:teacher,staff,headmaster,admin')->group(function () {
        Route::get('/gtk/absences', [GtkAbsenceController::class, 'index'])->name('gtk.absences.index');
        Route::get('/gtk/absences/create', [GtkAbsenceController::class, 'create'])->name('gtk.absences.create');
        Route::post('/gtk/absences', [GtkAbsenceController::class, 'store'])->name('gtk.absences.store');
        Route::get('/gtk/absences/{absence}', [GtkAbsenceController::class, 'show'])->name('gtk.absences.show');
        Route::get('/gtk/absences/{absence}/evidence', [GtkAbsenceController::class, 'evidence'])->name('gtk.absences.evidence');
        Route::delete('/gtk/absences/{absence}', [GtkAbsenceController::class, 'cancel'])->name('gtk.absences.cancel');
    });
    Route::middleware('role:headmaster')->group(function () {
        Route::get('/headmaster/absences', [GtkAbsenceController::class, 'reviews'])->name('headmaster.absences.index');
        Route::post('/headmaster/absences/{absence}/decision', [GtkAbsenceController::class, 'decide'])->name('headmaster.absences.decide');
    });

    // Redirect dashboard berdasarkan role
    Route::get('/dashboard', function () {
        return match (auth()->user()->activeRole()) {
            'admin' => redirect()->route('admin.dashboard'),
            'headmaster' => redirect()->route('headmaster.dashboard'),
            'teacher' => redirect()->route('teacher.dashboard'),
            'staff' => redirect()->route('staff.dashboard'),
        };
    })->name('dashboard');

    // Admin
    Route::middleware('role:admin')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {

            Route::get('/student-progressions', [\App\Http\Controllers\Admin\StudentManagementController::class, 'index'])->name('student-progressions.index');
            Route::post('/student-management/preview', [\App\Http\Controllers\Admin\StudentManagementController::class, 'preview'])->name('student-management.preview');
            Route::get('/student-management/review/{draft}', [\App\Http\Controllers\Admin\StudentManagementController::class, 'review'])->name('student-management.review');
            Route::post('/student-management/review/{draft}', [\App\Http\Controllers\Admin\StudentManagementController::class, 'commit'])->name('student-management.commit');
            Route::get('/student-management/history', [\App\Http\Controllers\Admin\StudentManagementController::class, 'history'])->name('student-management.history');
            Route::get('/student-management/issues', [\App\Http\Controllers\Admin\StudentManagementController::class, 'issues'])->name('student-management.issues');
            Route::get('/student-management/issues/{issue}', [\App\Http\Controllers\Admin\StudentManagementController::class, 'reconcile'])->name('student-management.reconcile');
            Route::get('/students/{student}/status', [\App\Http\Controllers\Admin\StudentManagementController::class, 'status'])->name('students.status');
            Route::get('/classes/{class}/placement', [\App\Http\Controllers\Admin\StudentManagementController::class, 'placement'])->name('classes.placement');
            Route::get('/academic-years', [\App\Http\Controllers\Admin\StudentManagementController::class, 'years'])->name('academic-years.index');
            Route::post('/academic-years', [\App\Http\Controllers\Admin\StudentManagementController::class, 'createYear'])->name('student-progressions.years.store');
            Route::post('/academic-years/activate', [\App\Http\Controllers\Admin\StudentManagementController::class, 'activateYear'])->name('student-progressions.years.activate');
            Route::get('/schedules/assignments', [SchoolClassController::class, 'assignments'])->name('schedules.assignments');
            Route::post('/schedules/assignments', [SchoolClassController::class, 'assignment'])->name('schedules.assignments.store');

            Route::resource('subjects', SubjectController::class)->except(['show']);
            Route::resource('letter-types', LetterTypeController::class)->except(['show']);

            Route::resource('classes', SchoolClassController::class);
            Route::resource('rooms', RoomController::class)->except(['show']);
            Route::get('/kbm-times', [KbmTimeController::class, 'index'])->name('kbm-times.index');
            Route::put('/kbm-times', [KbmTimeController::class, 'update'])->name('kbm-times.update');

            Route::get('/school-profile', [SchoolProfileController::class, 'edit'])->name('school-profile.edit');
            Route::put('/school-profile', [SchoolProfileController::class, 'update'])->name('school-profile.update');

            Route::get('/dashboard', DashboardController::class)
                ->name('dashboard');
            Route::livewire('/gtks', 'admin.gtks.index')
                ->name('gtks.index');
            Route::livewire('/gtks/{gtk}', 'admin.gtks.show')
                ->name('gtks.show');
            Route::livewire('/schedules', 'admin.schedules.index')
                ->name('schedules.index');
            Route::livewire('/students', 'admin.students.index')
                ->name('students.index');
            Route::livewire('/students/import', 'admin.students.import')
                ->name('students.import');
            Route::get('/student-cards', StudentCardController::class)
                ->name('students.cards');
            Route::livewire('/student-attendances', 'admin.student-attendances.index')
                ->name('student-attendances.index');
            Route::livewire('/student-attendances/history', 'student-attendances.history')
                ->name('student-attendances.history');
            Route::livewire('/gtk-attendances', 'admin.gtk-attendances.index')
                ->name('gtk-attendances.index');
            Route::livewire('/holidays', 'admin.holidays.index')
                ->name('holidays.index');
            Route::livewire('/incoming-letters', 'admin.incoming-letters.index')
                ->name('incoming-letters.index');
            Route::livewire('/incoming-letters/{incomingLetter}/dispositions', 'admin.incoming-letters.dispositions')
                ->name('incoming-letters.dispositions');
            Route::livewire('/outgoing-letters', 'admin.outgoing-letters.index')
                ->name('outgoing-letters.index');
            Route::livewire('/outgoing-letters/{outgoingLetter}', 'admin.outgoing-letters.show')
                ->name('outgoing-letters.show');
            Route::get(
                '/outgoing-letters/{outgoingLetter}/word',
                [OutgoingLetterController::class, 'word']
            )->name('outgoing-letters.word');
            Route::get(
                '/outgoing-letters/{outgoingLetter}/preview',
                OutgoingLetterPreviewController::class
            )->name('outgoing-letters.preview');

            Route::get('/students/search', function (Request $request) {
                $search = $request->input('q', '');

                return Student::query()
                    ->where('is_active', true)
                    ->when($search, function ($query) use ($search) {
                        $query->where(function ($query) use ($search) {
                            $query->where('full_name', 'like', "%{$search}%")
                                ->orWhere('nis', 'like', "%{$search}%")
                                ->orWhere('nisn', 'like', "%{$search}%");
                        });
                    })
                    ->orderBy('full_name')
                    ->limit(20)
                    ->get()
                    ->map(fn ($student) => [
                        'id' => $student->id,
                        'text' => $student->full_name
                            .($student->nis ? " — NIS: {$student->nis}" : '')
                            .($student->nisn ? " — NISN: {$student->nisn}" : ''),
                    ]);
            })->name('students.search');
        });

    // gtk
    Route::middleware('role:teacher,staff,headmaster,admin')
        ->prefix('gtk')
        ->name('gtk.')
        ->group(function () {
            Route::livewire('/attendance', 'gtk.attendance.index')
                ->name('attendance.index');
            Route::get('/journals', [GtkJournalController::class, 'index'])->middleware('role:teacher,headmaster')->name('journals.index');
            Route::get('/journals/history', [GtkJournalHistoryController::class, 'teaching'])->middleware('role:teacher,headmaster')->name('journals.history');
            Route::post('/journals', [GtkJournalController::class, 'store'])->middleware('role:teacher,headmaster')->name('journals.store');
            Route::delete('/journals/{journal}', [GtkJournalController::class, 'destroy'])->middleware('role:teacher,headmaster')->name('journals.destroy');
            Route::get('/lckh', [GtkJournalController::class, 'lckh'])->name('lckh.index');
            Route::get('/lckh/history', [GtkJournalHistoryController::class, 'lckh'])->name('lckh.history');
            Route::post('/lckh', [GtkJournalController::class, 'storeLckh'])->name('lckh.store');
            Route::delete('/lckh/{journal}', [GtkJournalController::class, 'destroyLckh'])->name('lckh.destroy');
            Route::livewire('/attendance/history', 'gtk.attendance.history')
                ->name('attendance.history');
            Route::get('/reports/{kind}', [GtkWorkReviewController::class, 'downloadOwn'])
                ->whereIn('kind', ['attendance', 'lckh', 'teaching'])->name('reports.download');
            Route::livewire('/dispositions', 'gtk.dispositions.index')
                ->name('dispositions.index');
            Route::livewire('/dispositions/{disposition}', 'gtk.dispositions.show')
                ->name('dispositions.show');
        });

    // Kepala Madrasah
    Route::middleware('role:headmaster')
        ->prefix('headmaster')
        ->name('headmaster.')
        ->group(function () {

            Route::get('/work-reviews', [GtkWorkReviewController::class, 'index'])->name('work-reviews.index');
            Route::get('/work-reviews/{gtk}', [GtkWorkReviewController::class, 'show'])->name('work-reviews.show');
            Route::post('/work-reviews/{gtk}/approval', [GtkWorkReviewController::class, 'decide'])->name('work-reviews.decide');
            Route::post('/work-reviews/{gtk}/approve-selected', [GtkWorkReviewController::class, 'approveSelected'])->name('work-reviews.approve-selected');
            Route::get('/teaching-journals', [GtkWorkReviewController::class, 'teachers'])->name('teaching-journals.index');
            Route::get('/teaching-journals/{gtk}', [GtkWorkReviewController::class, 'teaching'])->name('teaching-journals.show');
            Route::get('/gtk-reports/{gtk}/{kind}', [GtkWorkReviewController::class, 'download'])->whereIn('kind', ['attendance', 'lckh', 'teaching'])->name('gtk-reports.download');
            Route::get('/journals', fn (Request $request) => to_route('headmaster.work-reviews.index', $request->only('month')))->name('journals.index');
            Route::get('/journals/{gtk}', fn (Request $request, Gtk $gtk) => to_route('headmaster.work-reviews.show', [$gtk] + $request->only('month')))->name('journals.show');
            Route::post('/journals/{gtk}/exceptions', [GtkJournalController::class, 'exception'])->name('journals.exception');

            Route::get('/dashboard', DashboardController::class)
                ->name('dashboard');
            Route::livewire('/student-attendances/history', 'student-attendances.history')
                ->name('student-attendances.history');
            Route::livewire('/incoming-letters', 'headmaster.incoming-letters.index')
                ->name('incoming-letters.index');
            Route::livewire('/incoming-letters/{incomingLetter}/dispositions', 'headmaster.incoming-letters.dispositions')
                ->name('incoming-letters.dispositions');
            Route::livewire('/outgoing-letters', 'headmaster.outgoing-letters.index')
                ->name('outgoing-letters.index');

            Route::livewire('/outgoing-letters/{outgoingLetter}', 'headmaster.outgoing-letters.show')
                ->name('outgoing-letters.show');
        });

    // Guru
    Route::middleware('role:teacher,headmaster')
        ->prefix('teacher')
        ->name('teacher.')
        ->group(function () {

            Route::get('/dashboard', DashboardController::class)
                ->middleware('role:teacher')->name('dashboard');
            Route::livewire('/schedules', 'teacher.schedules.index')
                ->name('schedules.index');
            Route::livewire('/student-attendances', 'admin.student-attendances.index')
                ->name('student-attendances.index');
            Route::livewire('/student-attendances/history', 'student-attendances.history')
                ->name('student-attendances.history');
        });

    // Staff
    Route::middleware('role:staff')
        ->prefix('staff')
        ->name('staff.')
        ->group(function () {
            Route::livewire('/gtk-attendances', 'admin.gtk-attendances.index')->name('gtk-attendances.index');
            Route::livewire('/student-attendances', 'admin.student-attendances.index')->name('student-attendances.index');
            Route::livewire('/student-attendances/history', 'student-attendances.history')->name('student-attendances.history');

            Route::get('/dashboard', DashboardController::class)
                ->name('dashboard');
            Route::livewire('/incoming-letters', 'admin.incoming-letters.index')->name('incoming-letters.index');
            Route::livewire('/incoming-letters/{incomingLetter}/dispositions', 'admin.incoming-letters.dispositions')->name('incoming-letters.dispositions');
            Route::livewire('/outgoing-letters', 'admin.outgoing-letters.index')->name('outgoing-letters.index');
            Route::livewire('/outgoing-letters/{outgoingLetter}', 'admin.outgoing-letters.show')->name('outgoing-letters.show');
            Route::get('/outgoing-letters/{outgoingLetter}/word', [OutgoingLetterController::class, 'word'])->name('outgoing-letters.word');
            Route::get('/outgoing-letters/{outgoingLetter}/preview', OutgoingLetterPreviewController::class)->name('outgoing-letters.preview');
        });
});

// Route::get('/verify/{outgoingLetter}', function (
//     \App\Models\OutgoingLetter $outgoingLetter
// ) {
//     $outgoingLetter->load([
//         'letterType',
//         'creator',
//     ]);

//     return view('verify-letter', compact('outgoingLetter'));
// })->name('letters.verify');

Route::get('/verify/{token}', LetterVerificationController::class)
    ->name('letters.verify');

require __DIR__.'/settings.php';
