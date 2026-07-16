<?php

use App\Http\Controllers\Admin\UserAdministrationController;
use App\Http\Controllers\BandTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JamSessionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SetController;
use App\Http\Controllers\SongRequestController;
use App\Http\Controllers\SlotAssignmentController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\SongController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/sessions');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserAdministrationController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}', [UserAdministrationController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/password-reset', [UserAdministrationController::class, 'sendPasswordResetLink'])->name('users.password-reset');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('sessions', JamSessionController::class)
        ->except(['create', 'edit'])
        ->parameters(['sessions' => 'jamSession']);

    Route::post('/sessions/{jamSession}/sets', [SetController::class, 'store'])->name('sets.store');
    Route::patch('/sets/{set}', [SetController::class, 'update'])->name('sets.update');
    Route::delete('/sets/{set}', [SetController::class, 'destroy'])->name('sets.destroy');
    Route::post('/sets/{set}/song-requests', [SongRequestController::class, 'store'])->name('song-requests.store');

    Route::post('/sets/{set}/songs', [SongController::class, 'store'])->name('songs.store');
    Route::patch('/songs/{song}', [SongController::class, 'update'])->name('songs.update');
    Route::delete('/songs/{song}', [SongController::class, 'destroy'])->name('songs.destroy');

    Route::patch('/song-requests/{songRequest}/respond', [SongRequestController::class, 'respond'])->name('song-requests.respond');

    Route::post('/songs/{song}/slots', [SlotController::class, 'store'])->name('slots.store');
    Route::post('/slots/{slot}/take', [SlotController::class, 'take'])->name('slots.take');
    Route::patch('/slots/{slot}', [SlotController::class, 'update'])->name('slots.update');
    Route::delete('/slots/{slot}', [SlotController::class, 'destroy'])->name('slots.destroy');

    Route::post('/slots/{slot}/requests', [SlotAssignmentController::class, 'request'])->name('slot-assignments.request');
    Route::post('/slots/{slot}/proposals', [SlotAssignmentController::class, 'propose'])->name('slot-assignments.propose');
    Route::patch('/slot-assignments/{slotAssignment}/respond', [SlotAssignmentController::class, 'respond'])->name('slot-assignments.respond');

    Route::resource('band-templates', BandTemplateController::class)->except(['show', 'create', 'edit']);
});

require __DIR__.'/auth.php';
