<?php

use App\Http\Controllers\Admin\UserAdministrationController;
use App\Http\Controllers\BandTemplateController;
use App\Http\Controllers\DeezerLookupController;
use App\Http\Controllers\JamRegisterController;
use App\Http\Controllers\JamSessionController;
use App\Http\Controllers\MySignupsController;
use App\Http\Controllers\MySetsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SetController;
use App\Http\Controllers\SongRequestController;
use App\Http\Controllers\SlotAssignmentController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\UserDirectoryController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/sessions');

Route::get('/jam-register', [JamRegisterController::class, 'index'])->name('jam-register.index');
Route::get('/jam-register/users', [JamRegisterController::class, 'users'])->name('jam-register.users');
Route::get('/jam-register/sessions/{jamSession}/users/{user}/status', [JamRegisterController::class, 'status'])->name('jam-register.status');
Route::post('/jam-register/sessions/{jamSession}/check-in', [JamRegisterController::class, 'signIn'])->name('jam-register.sign-in');
Route::post('/jam-register/sessions/{jamSession}/check-out/{user}', [JamRegisterController::class, 'signOut'])->name('jam-register.sign-out');

Route::middleware('auth')->group(function () {
    Route::get('/my-signups', MySignupsController::class)->name('my-signups.index');
    Route::get('/my-sets', MySetsController::class)->name('my-sets.index');
    Route::get('/directory', UserDirectoryController::class)->name('directory.index');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserAdministrationController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}', [UserAdministrationController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/role', [UserAdministrationController::class, 'toggleRole'])->name('users.toggle-role');
        Route::post('/users/{user}/password-reset', [UserAdministrationController::class, 'sendPasswordResetLink'])->name('users.password-reset');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('sessions', JamSessionController::class)
        ->except(['create', 'edit'])
        ->parameters(['sessions' => 'jamSession']);
    Route::get('/sessions/{jamSession}/sets', [JamSessionController::class, 'sets'])->name('sessions.sets');
    Route::get('/sessions/{jamSession}/check-ins', [JamRegisterController::class, 'attendees'])->name('sessions.check-ins');
    Route::post('/sessions/{jamSession}/check-ins/sign-out-all', [JamRegisterController::class, 'signOutAll'])->name('sessions.check-ins.sign-out-all');

    Route::post('/sessions/{jamSession}/sets', [SetController::class, 'store'])->name('sets.store');
    Route::get('/sets/{set}/summary', [SetController::class, 'summary'])->name('sets.summary');
    Route::patch('/sets/{set}', [SetController::class, 'update'])->name('sets.update');
    Route::delete('/sets/{set}', [SetController::class, 'destroy'])->name('sets.destroy');
    Route::post('/sets/{set}/song-requests', [SongRequestController::class, 'store'])->name('song-requests.store');

    Route::post('/sets/{set}/songs', [SongController::class, 'store'])->name('songs.store');
    Route::patch('/sets/{set}/songs/reorder', [SongController::class, 'reorder'])->name('songs.reorder');
    Route::patch('/songs/{song}', [SongController::class, 'update'])->name('songs.update');
    Route::delete('/songs/{song}', [SongController::class, 'destroy'])->name('songs.destroy');

    Route::patch('/song-requests/{songRequest}/respond', [SongRequestController::class, 'respond'])->name('song-requests.respond');

    Route::post('/songs/{song}/slots', [SlotController::class, 'store'])->name('slots.store');
    Route::post('/slots/{slot}/take', [SlotController::class, 'take'])->name('slots.take');
    Route::post('/slots/{slot}/release', [SlotController::class, 'release'])->name('slots.release');
    Route::patch('/slots/{slot}', [SlotController::class, 'update'])->name('slots.update');
    Route::delete('/slots/{slot}', [SlotController::class, 'destroy'])->name('slots.destroy');

    Route::post('/slots/{slot}/requests', [SlotAssignmentController::class, 'request'])->name('slot-assignments.request');
    Route::post('/slots/{slot}/proposals', [SlotAssignmentController::class, 'propose'])->name('slot-assignments.propose');
    Route::patch('/slot-assignments/{slotAssignment}/respond', [SlotAssignmentController::class, 'respond'])->name('slot-assignments.respond');

    Route::get('/lookups/deezer/artists', [DeezerLookupController::class, 'artists'])->name('lookups.deezer.artists');
    Route::get('/lookups/deezer/tracks', [DeezerLookupController::class, 'tracks'])->name('lookups.deezer.tracks');

    Route::resource('band-templates', BandTemplateController::class)->except(['show', 'create', 'edit']);
});

require __DIR__.'/auth.php';
