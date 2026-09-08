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
