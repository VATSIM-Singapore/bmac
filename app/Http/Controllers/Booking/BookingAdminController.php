<?php

namespace App\Http\Controllers\Booking;

use Carbon\Carbon;
use App\Models\Event;
use App\Models\Flight;
use App\Models\Booking;
use Illuminate\View\View;
use App\Enums\BookingStatus;
use Illuminate\Http\Request;
use App\Events\BookingChanged;
use App\Events\BookingDeleted;
use App\Exports\BookingsExport;
use App\Imports\BookingsImport;
use App\Imports\BookingsValidationImport;
use App\Policies\BookingPolicy;
use App\Imports\FlightRouteAssign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\AdminController;
use App\Http\Requests\Booking\Admin\AutoAssign;
use App\Http\Requests\Booking\Admin\RouteAssign;
use App\Http\Requests\Booking\Admin\StoreBooking;
use App\Http\Requests\Booking\Admin\UpdateBooking;
use App\Http\Requests\Booking\Admin\ImportBookings;
use App\Services\CachedDataService;
use App\Services\RealFlightBookingValidator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BookingAdminController extends AdminController
{
    public function __construct()
    {
        $this->authorizeResource(BookingPolicy::class, 'booking');
    }

    public function create(Event $event, Request $request): View
    {
        $bulk = $request->bulk;
        $cachedDataService = new CachedDataService();

        $airports = $cachedDataService->getAirportsForSelect();
        $airlines = $cachedDataService->getAirlinesForSelect();

        return view('booking.admin.create', compact('event', 'airports', 'airlines', 'bulk'));
    }

    public function store(StoreBooking $request): RedirectResponse
    {
        // Handle empty airline_id before processing
        $data = $request->all();
        if (isset($data['airline_id']) && $data['airline_id'] === '') {
            $data['airline_id'] = null;
        }

        $event = Event::whereKey($request->id)->first();
        if ($request->bulk) {
            $event_start = Carbon::createFromFormat(
                'Y-m-d H:i',
                $event->startEvent->toDateString() . ' ' . $request->start
            );
            $event_end = Carbon::createFromFormat('Y-m-d H:i', $event->endEvent->toDateString() . ' ' . $request->end);
            $separation = $request->separation * 60;
            $count = 0;
            for (; $event_start <= $event_end; $event_start->addSeconds($separation)) {
                $time = $event_start->copy();
                if ($time->second >= 30) {
                    $time->addMinute();
                }
                $time->second = 0;

                if (!Flight::whereHas('booking', function ($query) use ($request) {
                    $query->where('event_id', $request->id);
                })->where([
                    'ctot' => $time,
                    'dep' => $request->dep,
                ])->first()) {
                    Booking::create([
                        'event_id' => $request->id,
                        'is_editable' => $request->is_editable,
                    ])->flights()->create([
                        'dep' => $request->dep,
                        'arr' => $request->arr,
                        'ctot' => $time,
                        'notes' => $request->notes ?? null,
                    ]);

                    $count++;
                }
            }
            flashMessage('success', __('Done'), __(':count slots have been created!', ['count' => $count]));
        } else {
            $booking = new Booking([
                'is_editable' => $request->is_editable,
                'callsign' => $request->callsign,
                'acType' => $request->acType,
                'airline_id' => $data['airline_id'],
            ]);

            $booking->event()->associate($request->id)->save();
            $flightAttributes = [
                'dep' => $request->dep,
                'arr' => $request->arr,
                'route' => $request->route,
                'oceanicFL' => $request->oceanicFL,
                'notes' => $request->notes ?? null,
            ];

            if ($request->ctot) {
                $flightAttributes['ctot'] = Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $event->startEvent->toDateString() . ' ' . $request->ctot
                );
            }

            if ($request->eta) {
                $flightAttributes['eta'] = Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $event->startEvent->toDateString() . ' ' . $request->eta
                );
            }

            $booking->flights()->create($flightAttributes);

            flashMessage('success', __('Done'), __('Slot created'));
        }
        return to_route('bookings.event.index', $event);
    }

    public function edit(Booking $booking): View|RedirectResponse
    {
        if ($booking->event->endEvent >= now()) {
            $cachedDataService = new CachedDataService();

            $airports = $cachedDataService->getAirportsForSelect();
            $airlines = $cachedDataService->getAirlinesForSelect();

            $flight = $booking->flights()->first();
            $booking->load('airline'); // Ensure airline relationship is loaded
            return view('booking.admin.edit', compact('booking', 'airports', 'airlines', 'flight'));
        }
        flashMessage('danger', __('Danger'), __('Booking can no longer be edited'));
        return back();
    }

    public function update(UpdateBooking $request, Booking $booking): RedirectResponse
    {
        // Handle empty airline_id before processing
        $data = $request->all();
        if (isset($data['airline_id']) && $data['airline_id'] === '') {
            $data['airline_id'] = null;
        }

        $shouldSendEmail = false;
        if (!empty($booking->user) && $request->notify_user) {
            $shouldSendEmail = true;
        }
        /* @var Flight $flight */
        $flight = $booking->flights()->first();
        $booking->fill([
            'is_editable' => $request->is_editable,
            'callsign' => $request->callsign,
            'acType' => $request->acType,
            'airline_id' => $data['airline_id'],
            'final_information_email_sent_at' => null
        ]);

        $flightAttributes = [
            'dep' => $request->dep,
            'arr' => $request->arr,
            'route' => $request->route,
            'oceanicFL' => $request->oceanicFL,
            'oceanicTrack' => $request->oceanicTrack,
            'notes' => $request->notes,
        ];

        if ($request->ctot) {
            $flightAttributes['ctot'] = Carbon::createFromFormat(
                'Y-m-d H:i',
                $booking->event->startEvent->toDateString() . ' ' . $request->ctot
            );
        } else {
            $flightAttributes['ctot'] = null;
        }

        if ($request->eta) {
            $flightAttributes['eta'] = Carbon::createFromFormat(
                'Y-m-d H:i',
                $booking->event->startEvent->toDateString() . ' ' . $request->eta
            );
        } else {
            $flightAttributes['eta'] = null;
        }

        $flight->fill($flightAttributes);

        if ($shouldSendEmail) {
            $changes = collect();
            foreach ($booking->getDirty() as $key => $value) {
                $changes->push(
                    ['name' => $key, 'old' => $booking->getOriginal($key), 'new' => $value]
                );
            }
            foreach ($flight->getDirty() as $key => $value) {
                $changes->push(
                    ['name' => $key, 'old' => $flight->getOriginal($key), 'new' => $value]
                );
            }
            if (!empty($request->message)) {
                $changes->push(
                    ['name' => 'message', 'new' => $request->message]
                );
            }
        }

        // Validate Real Flight Operations booking restrictions for admin update
        if ($booking->user) {
            $validator = new RealFlightBookingValidator();
            $validationResult = $validator->validateBooking($booking->user, $booking->event, $flight);

            if (!$validationResult->isSuccess()) {
                flashMessage('danger', __('Booking Restricted'), $validationResult->errorMessage);
                return to_route('bookings.event.index', $booking->event);
            }
        }

        $booking->save();
        $flight->save();
        if ($shouldSendEmail) {
            event(new BookingChanged($booking, $changes));
        }
        flashMessage('success', 'Booking changed', __('Booking has been changed!'));
        return to_route('bookings.event.index', $booking->event);
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        if ($booking->event->endEvent >= now()) {
            if (!empty($booking->user)) {
                event(new BookingDeleted($booking->event, $booking->user));
            }
            $booking->delete();
            flashMessage('success', 'Booking deleted!', __('Booking has been deleted.'));
            return to_route('bookings.event.index', $booking->event);
        }
        flashMessage('danger', __('Danger'), __('Booking can no longer be deleted'));
        return back();
    }

    public function export(Event $event, Request $request): BinaryFileResponse
    {
        activity()
            ->by(auth()->user())
            ->on($event)
            ->log('Export triggered');

        return (new BookingsExport($event, $request->vacc))->download('bookings.csv');
    }

    public function importForm(Event $event)
    {
        return view('event.admin.import', compact('event'));
    }

    public function import(ImportBookings $request, Event $event): RedirectResponse
    {
        activity()
            ->by(auth()->user())
            ->on($event)
            ->log('Import triggered');

        $file = $request->file('file');

        // First, validate the file to check for invalid airlines
        $validationImport = new BookingsValidationImport($event);
        $validationImport->validateFile($file);

        // Check if there are invalid airlines
        if ($validationImport->hasInvalidAirlines()) {
            $invalidAirlines = $validationImport->getInvalidAirlines();
            $airlineList = implode(', ', $invalidAirlines);

            // Store the file temporarily and invalid airlines in session for confirmation
            $tempPath = $file->store('temp');
            session()->flash('invalid_airlines', $invalidAirlines);
            session()->flash('import_file_path', $tempPath);
            session()->flash('event_id', $event->id);

            flashMessage(
                'warning',
                __('Invalid Airlines Detected'),
                __('The following airlines were not recognized: ' . $airlineList . '. Do you want to proceed with the import?')
            );

            return to_route('admin.bookings.import.confirm', $event);
        }

        // No invalid airlines, proceed with import
        $this->processImport($file, $event);

        flashMessage('success', __('Flights imported'), __('Flights have been imported'));
        return to_route('bookings.event.index', $event);
    }

    /**
     * Show confirmation page for invalid airlines
     */
    public function importConfirm(Event $event): View
    {
        $invalidAirlines = session('invalid_airlines', []);
        return view('event.admin.import-confirm', compact('event', 'invalidAirlines'));
    }

    /**
     * Process the import after confirmation
     */
    public function importProcess(Request $request, Event $event): RedirectResponse
    {
        $tempPath = session('import_file_path');
        $invalidAirlines = session('invalid_airlines', []);

        if (!$tempPath) {
            flashMessage('error', __('Import Failed'), __('Import file not found. Please try again.'));
            return to_route('admin.bookings.importForm', $event);
        }

        // Get the full path to the stored file
        $fullPath = Storage::path($tempPath);

        // Process the import using the stored file
        $import = new BookingsImport($event);
        $import->import($fullPath);

        // Clean up the temporary file
        Storage::delete($tempPath);

        // Clear session data
        session()->forget(['invalid_airlines', 'import_file_path', 'event_id']);

        if (!empty($invalidAirlines)) {
            $airlineList = implode(', ', $invalidAirlines);
            flashMessage(
                'success',
                __('Import Completed'),
                __('Import completed successfully. The following airlines were not recognized and were set to "No airline": ' . $airlineList)
            );
        } else {
            flashMessage('success', __('Flights imported'), __('Flights have been imported'));
        }

        return to_route('bookings.event.index', $event);
    }

    /**
     * Process the actual import
     */
    private function processImport($file, Event $event): void
    {
        $import = new BookingsImport($event);
        $import->import($file);

        // Clean up the uploaded file
        if (is_object($file) && method_exists($file, 'getRealPath')) {
            Storage::delete($file->getRealPath());
        }
    }

    public function adminAutoAssignForm(Event $event): View
    {
        return view('event.admin.autoAssign', compact('event'));
    }

    public function adminAutoAssign(AutoAssign $request, Event $event): RedirectResponse
    {
        // @TODO Optimise this, for now it's a ugly fix
        $bookings = $event->bookings()
            ->with(['flights' => function ($query) {
                $query->orderBy('ctot');
            }]);

        if (!$request->checkAssignAllFlights) {
            $bookings = $bookings->where('status', BookingStatus::BOOKED->value);
        }
        $bookings = $bookings->get();
        $count = 0;
        $flOdd = $request->maxFL;
        $flEven = $request->minFL;
        foreach ($bookings as $booking) {
            $flight = $booking->flights()->first();
            $count++;
            if ($count % 2 == 0) {
                $flight->fill([
                    'oceanicTrack' => $request->oceanicTrack2,
                    'route' => $request->route2,
                    'oceanicFL' => $flEven,
                ]);
                $flEven = $flEven + 10;
                if ($flEven > $request->maxFL) {
                    $flEven = $request->minFL;
                }
            } else {
                $flight->fill([
                    'oceanicTrack' => $request->oceanicTrack1,
                    'route' => $request->route1,
                    'oceanicFL' => $flOdd,
                ]);
                $flOdd = $flOdd - 10;
                if ($flOdd < $request->minFL) {
                    $flOdd = $request->maxFL;
                }
            }
            $flight->save();
        }
        flashMessage('success', __('Bookings changed'), __(':count bookings have been Auto-Assigned a FL, and route', ['count' => $count]));
        activity()
            ->by(auth()->user())
            ->on($event)
            ->withProperties(
                [
                    'Track 1' => $request->oceanicTrack1,
                    'Route 1' => $request->route1,
                    'Track 2' => $request->oceanicTrack2,
                    'Route 2' => $request->route2,
                    'count' => $count,
                ]
            )
            ->log('Flights auto-assigned');
        return to_route('admin.events.index');
    }

    public function routeAssignForm(Event $event): View
    {
        return view('event.admin.routeAssign', compact('event'));
    }


    public function routeAssign(RouteAssign $request, Event $event): RedirectResponse
    {
        activity()
            ->by(auth()->user())
            ->on($event)
            ->log('Route assign triggered');
        $file = $request->file('file');
        (new FlightRouteAssign())->import($file);
        Storage::delete($file);
        flashMessage('success', __('Routes assigned'), __('Routes have been assigned to flights'));
        return to_route('bookings.event.index', $event);
    }
}
