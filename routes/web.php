<?php

use App\Http\Controllers\Admin\AttachmentAdministrationController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SlotTypeConflictController;
use App\Http\Controllers\Admin\SlotTypeController;
use App\Http\Controllers\Admin\UserAdministrationController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BandTemplateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeezerLookupController;
use App\Http\Controllers\JamRegisterController;
use App\Http\Controllers\JamSessionController;
use App\Http\Controllers\JamStandardCapabilityController;
use App\Http\Controllers\JamStandardController;
use App\Http\Controllers\JamStandardQuickSetController;
use App\Http\Controllers\JamStandardSongRequestController;
use App\Http\Controllers\LiveJamController;
use App\Http\Controllers\MySetsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPushSubscriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SetCollaboratorController;
use App\Http\Controllers\SetController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\SlotAssignmentController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\SlotFinderController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\SongRequestController;
use App\Http\Controllers\UserDirectoryController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

Route::get('/', function (Request $request): View|RedirectResponse {
    if ($request->user()) {
        return to_route('dashboard');
    }

    return view('welcome');
})->name('home');

Route::view('/logged-out', 'auth.logged-out')->name('logged-out');

Route::get('/jam-register', [JamRegisterController::class, 'index'])->name('jam-register.index');
Route::get('/jam-register/users', [JamRegisterController::class, 'users'])->name('jam-register.users');
Route::get('/jam-register/sessions/{jamSession}/users/{user}/status', [JamRegisterController::class, 'status'])->name('jam-register.status');
Route::post('/jam-register/sessions/{jamSession}/check-in', [JamRegisterController::class, 'signIn'])->name('jam-register.sign-in');
Route::post('/jam-register/sessions/{jamSession}/check-out/{user}', [JamRegisterController::class, 'signOut'])->name('jam-register.sign-out');
Route::get('/jam-register/{code}', [JamRegisterController::class, 'register'])->where('code', '[A-Za-z0-9]{4}')->name('jam-register.session');

Route::get('/sessions/{jamSession}/live/dashboard', [LiveJamController::class, 'dashboard'])->name('sessions.live.dashboard');
Route::get('/sessions/{jamSession}/live/data', [LiveJamController::class, 'data'])->name('sessions.live.data');
Route::get('/live/{liveCode}', [LiveJamController::class, 'shortDashboard'])->whereAlphaNumeric('liveCode')->name('sessions.live.short');

Route::get('/share/session/{jamSession}', [ShareController::class, 'session'])->name('share.session');
Route::get('/share/set/{set}', [ShareController::class, 'set'])->name('share.set');
Route::view('/about', 'static.about')->name('about');
Route::view('/privacy-policy', 'static.privacy')->name('privacy');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/dashboard/get-started/dismiss', [DashboardController::class, 'dismissGetStartedQuest'])->name('dashboard.get-started.dismiss');
    Route::get('/find-a-slot', SlotFinderController::class)->name('slot-finder.index');
    Route::redirect('/sit-in', '/find-a-slot')->name('sit-in.index');

    Route::view('/help', 'static.help')->name('help');

    Route::get('/my-sets', MySetsController::class)->name('my-sets.index');
    Route::get('/my-sets/count', [MySetsController::class, 'count'])->name('my-sets.count');
    Route::get('/jam-standards', [JamStandardController::class, 'index'])->name('jam-standards.index');
    Route::post('/jam-standards', [JamStandardController::class, 'store'])->name('jam-standards.store');
    Route::put('/jam-standards/{jamStandardSong}', [JamStandardController::class, 'update'])->name('jam-standards.update');
    Route::delete('/jam-standards/{jamStandardSong}', [JamStandardController::class, 'destroy'])->name('jam-standards.destroy');
    Route::get('/jam-standards/{jamStandardSong}/coverage', [JamStandardController::class, 'coverage'])->name('jam-standards.coverage');
    Route::post('/jam-standards/requests', [JamStandardSongRequestController::class, 'store'])->name('jam-standards.requests.store');
    Route::patch('/jam-standards/requests/{jamStandardSongRequest}', [JamStandardSongRequestController::class, 'respond'])->name('jam-standards.requests.respond');
    Route::delete('/jam-standards/requests/{jamStandardSongRequest}', [JamStandardSongRequestController::class, 'destroy'])->name('jam-standards.requests.destroy');
    Route::put('/jam-standards/{jamStandardSong}/capabilities', [JamStandardCapabilityController::class, 'update'])->name('jam-standards.capabilities.update');
    Route::post('/jam-standards/quick-set', [JamStandardQuickSetController::class, 'storeUser'])->name('jam-standards.quick-set.store');
    Route::post('/jam-standards/live-quick-set', [JamStandardQuickSetController::class, 'storeLive'])->name('jam-standards.live-quick-set.store');
    Route::get('/directory', UserDirectoryController::class)->name('directory.index');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::patch('/settings/{setting}', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/test-push', [SettingController::class, 'sendTestPush'])->name('settings.push-test');
        Route::post('/settings/test-email', [SettingController::class, 'sendTestEmail'])->name('settings.email-test');
        Route::post('/slot-types', [SlotTypeController::class, 'store'])->name('slot-types.store');
        Route::patch('/slot-types/{slotType}', [SlotTypeController::class, 'update'])->name('slot-types.update');

        Route::get('/slot-conflicts', [SlotTypeConflictController::class, 'index'])->name('slot-conflicts.index');
        Route::patch('/slot-conflicts/{slotType}', [SlotTypeConflictController::class, 'update'])->name('slot-conflicts.update');

        Route::get('/users', [UserAdministrationController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}', [UserAdministrationController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/role', [UserAdministrationController::class, 'toggleRole'])->name('users.toggle-role');
        Route::post('/users/{user}/password-reset', [UserAdministrationController::class, 'sendPasswordResetLink'])->name('users.password-reset');
        Route::get('/attachments', [AttachmentAdministrationController::class, 'index'])->name('attachments.index');
        Route::delete('/attachments/{attachment}', [AttachmentAdministrationController::class, 'destroy'])->name('attachments.destroy');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/privacy', [ProfileController::class, 'updatePrivacy'])->name('profile.privacy.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/seen', [NotificationController::class, 'markSeen'])->name('notifications.seen');
    Route::patch('/notifications/{notification}/dismiss', [NotificationController::class, 'dismiss'])->name('notifications.dismiss');
    Route::post('/notifications/push-subscriptions', [NotificationPushSubscriptionController::class, 'store'])->name('notifications.push-subscriptions.store');
    Route::delete('/notifications/push-subscriptions', [NotificationPushSubscriptionController::class, 'destroy'])->name('notifications.push-subscriptions.destroy');

    Route::get('/sessions/archive', [JamSessionController::class, 'archive'])->name('sessions.archive');
    Route::resource('sessions', JamSessionController::class)
        ->except(['create', 'edit'])
        ->parameters(['sessions' => 'jamSession']);
    Route::get('/sessions/{jamSession}/sets', [JamSessionController::class, 'sets'])->name('sessions.sets');
    Route::get('/sessions/{jamSession}/sets/{set}', [JamSessionController::class, 'setBody'])->name('sessions.sets.body');
    Route::get('/sessions/{jamSession}/activity', [JamSessionController::class, 'activity'])->name('sessions.activity');
    Route::get('/sessions/{jamSession}/check-ins', [JamRegisterController::class, 'attendees'])->name('sessions.check-ins');
    Route::get('/sessions/{jamSession}/check-ins/users', [JamRegisterController::class, 'availableUsers'])->name('sessions.check-ins.users');
    Route::post('/sessions/{jamSession}/check-ins/check-in', [JamRegisterController::class, 'manualSignIn'])->name('sessions.check-ins.sign-in');
    Route::post('/sessions/{jamSession}/check-ins/{user}/check-out', [JamRegisterController::class, 'manualSignOut'])->name('sessions.check-ins.sign-out');
    Route::post('/sessions/{jamSession}/check-ins/sign-out-all', [JamRegisterController::class, 'signOutAll'])->name('sessions.check-ins.sign-out-all');

    Route::get('/sessions/{jamSession}/live', [LiveJamController::class, 'manage'])->name('sessions.live.manage');
    Route::get('/sessions/{jamSession}/live/quick-set-data', [LiveJamController::class, 'quickSetData'])->name('sessions.live.quick-set-data');
    Route::post('/sessions/{jamSession}/live/manager', [LiveJamController::class, 'claimManager'])->name('sessions.live.manager.claim');
    Route::delete('/sessions/{jamSession}/live/manager', [LiveJamController::class, 'releaseManager'])->name('sessions.live.manager.release');
    Route::post('/sessions/{jamSession}/live/update', [LiveJamController::class, 'update'])->name('sessions.live.update');
    Route::delete('/sessions/{jamSession}/live/clear', [LiveJamController::class, 'clear'])->name('sessions.live.clear');

    Route::post('/sessions/{jamSession}/sets', [SetController::class, 'store'])->name('sets.store');
    Route::get('/sets/{set}/summary', [SetController::class, 'summary'])->name('sets.summary');
    Route::patch('/sets/{set}', [SetController::class, 'update'])->name('sets.update');
    Route::delete('/sets/{set}', [SetController::class, 'destroy'])->name('sets.destroy');
    Route::get('/sets/{set}/collaborators/users', [SetCollaboratorController::class, 'users'])->name('sets.collaborators.users');
    Route::put('/sets/{set}/collaborators', [SetCollaboratorController::class, 'update'])->name('sets.collaborators.update');
    Route::post('/sets/{set}/song-requests', [SongRequestController::class, 'store'])->name('song-requests.store');
    Route::get('/sets/{set}/attachments', [AttachmentController::class, 'setIndex'])->name('sets.attachments.index');
    Route::post('/sets/{set}/attachments', [AttachmentController::class, 'setStore'])->name('sets.attachments.store');

    Route::post('/sets/{set}/songs', [SongController::class, 'store'])->name('songs.store');
    Route::patch('/sets/{set}/songs/reorder', [SongController::class, 'reorder'])->name('songs.reorder');
    Route::patch('/songs/{song}', [SongController::class, 'update'])->name('songs.update');
    Route::delete('/songs/{song}', [SongController::class, 'destroy'])->name('songs.destroy');
    Route::get('/songs/{song}/attachments', [AttachmentController::class, 'songIndex'])->name('songs.attachments.index');
    Route::post('/songs/{song}/attachments', [AttachmentController::class, 'songStore'])->name('songs.attachments.store');

    Route::patch('/song-requests/{songRequest}/respond', [SongRequestController::class, 'respond'])->name('song-requests.respond');

    Route::post('/songs/{song}/slots', [SlotController::class, 'store'])->name('slots.store');
    Route::patch('/songs/{song}/slots/reorder', [SlotController::class, 'reorder'])->name('slots.reorder');
    Route::post('/slots/{slot}/take', [SlotController::class, 'take'])->name('slots.take');
    Route::post('/slots/{slot}/release', [SlotController::class, 'release'])->name('slots.release');
    Route::patch('/slots/{slot}', [SlotController::class, 'update'])->name('slots.update');
    Route::delete('/slots/{slot}', [SlotController::class, 'destroy'])->name('slots.destroy');
    Route::get('/slots/{slot}/attachments', [AttachmentController::class, 'slotIndex'])->name('slots.attachments.index');
    Route::post('/slots/{slot}/attachments', [AttachmentController::class, 'slotStore'])->name('slots.attachments.store');

    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');

    Route::post('/slots/{slot}/requests', [SlotAssignmentController::class, 'request'])->name('slot-assignments.request');
    Route::post('/slots/{slot}/proposals', [SlotAssignmentController::class, 'propose'])->name('slot-assignments.propose');
    Route::patch('/slot-assignments/{slotAssignment}/respond', [SlotAssignmentController::class, 'respond'])->name('slot-assignments.respond');

    Route::get('/lookups/deezer/artists', [DeezerLookupController::class, 'artists'])->name('lookups.deezer.artists');
    Route::get('/lookups/deezer/tracks', [DeezerLookupController::class, 'tracks'])->name('lookups.deezer.tracks');

    Route::resource('band-templates', BandTemplateController::class)->except(['show', 'create', 'edit']);
});

require __DIR__.'/auth.php';
