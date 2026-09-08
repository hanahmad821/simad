<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin', function () {
        return 'Admin';
    });
});
Route::middleware(['auth', 'role:teacher'])->group(function () {
    Route::get('/teacher', function () {
        return 'Teacher';
    });
});
Route::middleware(['auth', 'role:headmaster'])->group(function () {
    Route::get('/headmaster', function () {
        return 'Headmaster';
    });
});
Route::middleware(['auth', 'role:admin,headmaster'])->group(function () {
    Route::get('/reports', function () {
        return 'Reports';
    });
});

require __DIR__.'/settings.php';
