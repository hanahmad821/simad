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
        });

        //gtk
        Route::middleware('role:teacher,staff,headmaster')
        ->prefix('gtk')
        ->name('gtk.')
        ->group(function () {
            Route::livewire('/attendance', 'gtk.attendance.index')
            ->name('attendance.index');
            Route::livewire('/attendance/history', 'gtk.attendance.history')
            ->name('attendance.history');
        });

    // Kepala Madrasah
    Route::middleware('role:headmaster')
        ->prefix('headmaster')
        ->name('headmaster.')
        ->group(function () {

            Route::view('/dashboard', 'headmaster.dashboard')
                ->name('dashboard');
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

require __DIR__.'/settings.php';
