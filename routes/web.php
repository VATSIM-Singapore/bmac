<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Faq\FaqController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Event\EventController;
use App\Http\Controllers\Faq\FaqAdminController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Event\EventAdminController;
use App\Http\Controllers\Airport\AirportAdminController;
use App\Http\Controllers\Airline\AirlineAdminController;
use App\Http\Controllers\Booking\BookingAdminController;
use App\Http\Controllers\AirportLink\AirportLinkAdminController;
use App\Http\Controllers\EventLink\EventLinkAdminController;
use App\Http\Controllers\BayController;
use App\Http\Controllers\User\UserAdminController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/login', [LoginController::class, 'login'])->name('login');
Route::get('/logout', [LoginController::class, 'logout'])->name('logout');

// Admin routes
Route::group(['as' => 'admin.', 'prefix' => 'admin', 'middleware' => 'auth.isAdmin'], function () {
    // Airports
    Route::post('airports/destroy-unused', [AirportAdminController::class, 'destroyUnused'])->name('airports.destroyUnused');
    Route::resource('airports', AirportAdminController::class);

    // Bays
    Route::get('airports/{airport}/bays', [BayController::class, 'index'])->name('airports.bays.index');
    Route::post('airports/{airport}/bays', [BayController::class, 'store'])->name('airports.bays.store');
    Route::put('airports/{airport}/bays/{bay}', [BayController::class, 'update'])->name('airports.bays.update');
    Route::delete('airports/{airport}/bays/{bay}', [BayController::class, 'destroy'])->name('airports.bays.destroy');

    // Airlines
    Route::resource('airlines', AirlineAdminController::class)->except(['show']);

    // AirportLinks
    Route::resource('airportLinks', AirportLinkAdminController::class)->except(['show']);

    // EventLinks
    Route::resource('eventLinks', EventLinkAdminController::class)->except(['show']);

    // Users
    Route::get('users', [UserAdminController::class, 'index'])->name('users.index');
    Route::post('users/{user}/toggle-admin', [UserAdminController::class, 'toggleAdmin'])->name('users.toggleAdmin');

    // Faq
    Route::resource('faq', FaqAdminController::class)->except('show');
    Route::patch('faq/{faq}/toggle-event/{event}', [FaqAdminController::class, 'toggleEvent'])->name('faq.toggleEvent');

    // Event
    Route::delete('events/{event}/delete-bookings', [EventAdminController::class, 'deleteAllBookings'])->name('events.delete-bookings');
    Route::resource('events', EventAdminController::class);
    Route::get('{event}/email', [EventAdminController::class, 'sendEmailForm'])->name('events.email.form');
    Route::patch('{event}/email', [EventAdminController::class, 'sendEmail'])->name('events.email');
    Route::patch(
        '{event}/email_final',
        [EventAdminController::class, 'sendFinalInformationMail']
    )->name('events.email.final');

    // Booking
    Route::resource('bookings', BookingAdminController::class)->except(['index', 'create', 'show']);
    Route::get('{event}/bookings/export/{vacc?}', [BookingAdminController::class, 'export'])->name('bookings.export');
    Route::get('{event}/bookings/create/{bulk?}', [BookingAdminController::class, 'create'])->name('bookings.create');
    Route::get('{event}/bookings/import', [BookingAdminController::class, 'importForm'])->name('bookings.importForm');
    Route::post('{event}/bookings/import', [BookingAdminController::class, 'import'])->name('bookings.import');
    Route::get('{event}/bookings/import/confirm', [BookingAdminController::class, 'importConfirm'])->name('bookings.import.confirm');
    Route::post('{event}/bookings/import/process', [BookingAdminController::class, 'importProcess'])->name('bookings.import.process');
    Route::get(
        '{event}/bookings/auto-assign',
        [BookingAdminController::class, 'adminAutoAssignForm']
    )->name('bookings.autoAssignForm');
    Route::post(
        '{event}/bookings/auto-assign',
        [BookingAdminController::class, 'adminAutoAssign']
    )->name('bookings.autoAssign');
    Route::get(
        '{event}/bookings/route-assign',
        [BookingAdminController::class, 'routeAssignForm']
    )->name('bookings.routeAssignForm');
    Route::post(
        '{event}/bookings/route-assign',
        [BookingAdminController::class, 'routeAssign']
    )->name('bookings.routeAssign');

    // Bay Management Routes
    Route::get('{event}/bay-management', [App\Http\Controllers\Event\BayManagementController::class, 'index'])
        ->name('events.bay-management');
    Route::get('{event}/bay-management/flight-details/{flight}', [App\Http\Controllers\Event\BayManagementController::class, 'getFlightDetails'])
        ->name('events.bay-management.flight-details');
    Route::post('{event}/bay-management/flight-details/{flight}', [App\Http\Controllers\Event\BayManagementController::class, 'updateFlightDetails'])
        ->name('events.bay-management.update-flight-details');
    Route::post('{event}/bay-management/update-flight-assignment/{flight}', [App\Http\Controllers\Event\BayManagementController::class, 'updateFlightAssignment'])
        ->name('events.bay-management.update-flight-assignment');

    // Bay Blocking Routes
    Route::get('{event}/bay-management/blocked-bays', [App\Http\Controllers\Event\BayManagementController::class, 'getBlockedBays'])
        ->name('events.bay-management.blocked-bays');
    Route::post('{event}/bay-management/block-bay', [App\Http\Controllers\Event\BayManagementController::class, 'blockBay'])
        ->name('events.bay-management.block-bay');
    Route::delete('{event}/bay-management/unblock-bay', [App\Http\Controllers\Event\BayManagementController::class, 'unblockBay'])
        ->name('events.bay-management.unblock-bay');

    // Ad Hoc Flight Routes
    Route::post('{event}/bay-management/store-adhoc-flight', [App\Http\Controllers\Event\BayManagementController::class, 'storeAdhocFlight'])
        ->name('events.bay-management.store-adhoc-flight');
    Route::get('{event}/bay-management/get-adhoc-flight', [App\Http\Controllers\Event\BayManagementController::class, 'getAdhocFlight'])
        ->name('events.bay-management.get-adhoc-flight');
    Route::put('{event}/bay-management/update-adhoc-flight', [App\Http\Controllers\Event\BayManagementController::class, 'updateAdhocFlight'])
        ->name('events.bay-management.update-adhoc-flight');
    Route::delete('{event}/bay-management/delete-adhoc-flight', [App\Http\Controllers\Event\BayManagementController::class, 'deleteAdhocFlight'])
        ->name('events.bay-management.delete-adhoc-flight');
});

Route::resource('bookings', BookingController::class)->only(['show', 'edit', 'update']);
Route::get('/{event}/bookings/{filter?}', [BookingController::class, 'index'])->name('bookings.event.index');
Route::get('/bookings/{booking}/edit', [BookingController::class, 'edit'])
    ->middleware('auth.isLoggedIn')->name('bookings.edit');
Route::patch('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

Route::get('faq', FaqController::class)->name('faq');

Route::get('{event}', EventController::class)->name('events.show');

Route::middleware('auth.isLoggedIn')->group(function () {
    Route::prefix('user')->name('user.')
        ->group(function () {
            Route::get('settings', [UserController::class, 'showSettingsForm'])->name('settings');
            Route::patch('settings', [UserController::class, 'saveSettings'])->name('saveSettings');
        });
});
