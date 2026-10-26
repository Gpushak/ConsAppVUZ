<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Student\BookingController;
use App\Http\Controllers\Student\TeacherDirectoryController;
use App\Http\Controllers\Teacher\BookingController as TeacherBookingController;
use App\Http\Controllers\Teacher\SlotController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::post('/password', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware('password.changed')->group(function () {
        Route::get('/', [HomeController::class, 'index'])->name('home');

        Route::middleware('role:student')->group(function () {
            Route::get('/teachers', [TeacherDirectoryController::class, 'index'])->name('teachers.index');
            Route::post('/teachers/{teacher}/favorite', [TeacherDirectoryController::class, 'favorite'])->name('teachers.favorite');
            Route::post('/teachers/{teacher}/unfavorite', [TeacherDirectoryController::class, 'unfavorite'])->name('teachers.unfavorite');
            Route::get('/slots/{slot}', [BookingController::class, 'create'])->name('bookings.create');
            Route::post('/slots/{slot}/bookings', [BookingController::class, 'store'])->name('bookings.store');
            Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
            Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
            Route::get('/bookings/{booking}/cancel', [BookingController::class, 'cancelForm'])->name('bookings.cancel');
            Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel.store');
        });

        Route::middleware('role:teacher')->prefix('teacher')->group(function () {
            Route::get('/slots', [SlotController::class, 'index'])->name('teacher.slots.index');
            Route::get('/slots/create', [SlotController::class, 'create'])->name('teacher.slots.create');
            Route::post('/slots', [SlotController::class, 'store'])->name('teacher.slots.store');
            Route::get('/slots/{slot}/unpublish', [SlotController::class, 'unpublishForm'])->name('teacher.slots.unpublish.confirm');
            Route::post('/slots/{slot}/unpublish', [SlotController::class, 'unpublish'])->name('teacher.slots.unpublish');
            Route::get('/slots/{slot}/conduct', [SlotController::class, 'conductForm'])->name('teacher.slots.conduct');
            Route::post('/slots/{slot}/conduct', [SlotController::class, 'conduct'])->name('teacher.slots.conduct.store');
            Route::get('/bookings/{booking}/cancel', [TeacherBookingController::class, 'cancelForm'])->name('teacher.bookings.cancel');
            Route::post('/bookings/{booking}/cancel', [TeacherBookingController::class, 'cancel'])->name('teacher.bookings.cancel.store');
        });

        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
            Route::post('/teachers', [UserController::class, 'storeTeacher'])->name('admin.teachers.store');
            Route::get('/users/{user}/block', [UserController::class, 'blockForm'])->name('admin.users.block.confirm');
            Route::post('/users/{user}/block', [UserController::class, 'block'])->name('admin.users.block');
            Route::post('/users/{user}/unblock', [UserController::class, 'unblock'])->name('admin.users.unblock');
            Route::post('/users/{user}/password', [UserController::class, 'resetPassword'])->name('admin.users.password');
        });
    });
});
