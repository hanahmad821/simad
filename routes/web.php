<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {

    // Redirect dashboard berdasarkan role
    Route::get('/dashboard', function () {
        return match (auth()->user()->role) {
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

            Route::view('/dashboard', 'admin.dashboard')
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
            Route::livewire('/student-attendances', 'admin.student-attendances.index')
            ->name('student-attendances.index');
            Route::livewire('/gtk-attendances', 'admin.gtk-attendances.index')
            ->name('gtk-attendances.index');
            Route::livewire('/holidays', 'admin.holidays.index')
            ->name('holidays.index');
            Route::livewire('/incoming-letters', 'admin.incoming-letters.index')
            ->name('incoming-letters.index');
            Route::livewire('/incoming-letters/{incomingLetter}/dispositions','admin.incoming-letters.dispositions')
            ->name('incoming-letters.dispositions');
            Route::livewire('/outgoing-letters', 'admin.outgoing-letters.index')
            ->name('outgoing-letters.index');
            Route::livewire('/outgoing-letters/{outgoingLetter}', 'admin.outgoing-letters.show')
            ->name('outgoing-letters.show');
            Route::get('/outgoing-letters/{outgoingLetter}/word',
                [\App\Http\Controllers\Admin\OutgoingLetterController::class, 'word']
            )->name('outgoing-letters.word');



            Route::get('/students/search', function (\Illuminate\Http\Request $request) {
                $search = $request->input('q', '');

                return \App\Models\Student::query()
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
                            . ($student->nis ? " — NIS: {$student->nis}" : '')
                            . ($student->nisn ? " — NISN: {$student->nisn}" : ''),
                    ]);
            })->name('students.search');
        });

        //gtk
        Route::middleware('role:teacher,staff,headmaster,admin')
        ->prefix('gtk')
        ->name('gtk.')
        ->group(function () {
            Route::livewire('/attendance', 'gtk.attendance.index')
            ->name('attendance.index');
            Route::livewire('/attendance/history', 'gtk.attendance.history')
            ->name('attendance.history');
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

            Route::view('/dashboard', 'headmaster.dashboard')
                ->name('dashboard');
            Route::livewire('/outgoing-letters', 'headmaster.outgoing-letters.index')
            ->name('outgoing-letters.index');

            Route::livewire('/outgoing-letters/{outgoingLetter}', 'headmaster.outgoing-letters.show')
            ->name('outgoing-letters.show');
        });


    // Guru
    Route::middleware('role:teacher')
        ->prefix('teacher')
        ->name('teacher.')
        ->group(function () {

            Route::view('/dashboard', 'teacher.dashboard')
                ->name('dashboard');
        });


    // Staff
    Route::middleware('role:staff')
        ->prefix('staff')
        ->name('staff.')
        ->group(function () {

            Route::view('/dashboard', 'staff.dashboard')
                ->name('dashboard');
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

Route::get('/verify/{token}', \App\Http\Controllers\LetterVerificationController::class)
    ->name('letters.verify');

require __DIR__.'/settings.php';
