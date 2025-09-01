<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Bay;
use App\Models\Flight;
use App\Enums\EventType;
use App\Services\CachedDataService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BayManagementController extends Controller
{
    public function index(Event $event)
    {
        // Ensure this is a Real Flight Ops event with same dep/arr airport
        if ($event->event_type_id !== EventType::REALFLIGHTOPS->value) {
            abort(403, 'Bay management is only available for Real Flight Ops events.');
        }

        if (!$event->dep || !$event->arr || $event->dep !== $event->arr) {
            abort(403, 'Bay management is only available when departure and arrival airports are the same.');
        }

        // Get all bays for the event airport
        $cachedDataService = new CachedDataService();
        $bays = $cachedDataService->getSortedBays($event->dep);

        // Get all flights for this event that have bay assignments
        $flights = Flight::whereHas('booking', function ($query) use ($event) {
            $query->where('event_id', $event->id);
        })
            ->with(['booking', 'depBay', 'arrBay', 'airportDep', 'airportArr'])
            ->where(function ($query) {
                $query->whereNotNull('dep_bay')
                      ->orWhereNotNull('arr_bay');
            })
            ->get();

        // Determine time range from bay assignments
        $timeRange = $this->calculateTimeRange($event, $flights);

        // Generate 5-minute time slots
        $timeSlots = $this->generateTimeSlots($timeRange['start'], $timeRange['end']);

        // Build bay usage matrix
        $bayUsage = $this->buildBayUsageMatrix($bays, $flights, $timeSlots, $event);

        return view('admin.bay-management.index', compact(
            'event',
            'bays',
            'timeSlots',
            'bayUsage',
            'timeRange'
        ));
    }

    private function calculateTimeRange(Event $event, $flights)
    {
        // Start with event times with 1-hour buffer
        $startTime = $event->startEvent->copy()->subHour();
        $endTime = $event->endEvent->copy()->addHour();

        // Expand based on actual bay assignments
        foreach ($flights as $flight) {
            if ($flight->dep_bay_assigned_from) {
                $assignmentStart = Carbon::parse($flight->dep_bay_assigned_from);
                if ($assignmentStart->lt($startTime)) {
                    $startTime = $assignmentStart;
                }
            }

            if ($flight->dep_bay_assigned_to) {
                $assignmentEnd = Carbon::parse($flight->dep_bay_assigned_to);
                if ($assignmentEnd->gt($endTime)) {
                    $endTime = $assignmentEnd;
                }
            }

            if ($flight->arr_bay_assigned_from) {
                $assignmentStart = Carbon::parse($flight->arr_bay_assigned_from);
                if ($assignmentStart->lt($startTime)) {
                    $startTime = $assignmentStart;
                }
            }

            if ($flight->arr_bay_assigned_to) {
                $assignmentEnd = Carbon::parse($flight->arr_bay_assigned_to);
                if ($assignmentEnd->gt($endTime)) {
                    $endTime = $assignmentEnd;
                }
            }
        }

        return [
            'start' => $startTime,
            'end' => $endTime
        ];
    }

    private function generateTimeSlots(Carbon $start, Carbon $end)
    {
        $slots = collect();
        $current = $start->copy()->startOfHour();

        // Round to nearest 5-minute interval
        $minutes = $current->minute;
        $roundedMinutes = floor($minutes / 5) * 5;
        $current->minute($roundedMinutes)->second(0);

        while ($current->lte($end)) {
            $slots->push($current->copy());
            $current->addMinutes(5);
        }

        return $slots;
    }

    private function buildBayUsageMatrix($bays, $flights, $timeSlots, $event)
    {
        $matrix = [];

        // Initialize matrix
        foreach ($bays as $bay) {
            $matrix[$bay->id] = [];
            foreach ($timeSlots as $timeSlot) {
                $matrix[$bay->id][$timeSlot->format('Y-m-d H:i')] = [];
            }
        }

        // Fill matrix with flight assignments
        foreach ($flights as $flight) {
            $this->addFlightToMatrix($matrix, $flight, $timeSlots, $event, 'departure');
            $this->addFlightToMatrix($matrix, $flight, $timeSlots, $event, 'arrival');
        }

        return $matrix;
    }

    private function addFlightToMatrix(&$matrix, $flight, $timeSlots, $event, $type)
    {
        $bayId = null;
        $assignedFrom = null;
        $assignedTo = null;
        $isRelevantAirport = false;

        if ($type === 'departure' && $flight->dep_bay) {
            $bayId = $flight->dep_bay;
            $assignedFrom = $flight->dep_bay_assigned_from;
            $assignedTo = $flight->dep_bay_assigned_to;
            $isRelevantAirport = $flight->dep == $event->dep;
        } elseif ($type === 'arrival' && $flight->arr_bay) {
            $bayId = $flight->arr_bay;
            $assignedFrom = $flight->arr_bay_assigned_from;
            $assignedTo = $flight->arr_bay_assigned_to;
            $isRelevantAirport = $flight->arr == $event->arr;
        }

        if (!$bayId || !$assignedFrom || !$assignedTo || !$isRelevantAirport) {
            return;
        }

        $assignedFrom = Carbon::parse($assignedFrom);
        $assignedTo = Carbon::parse($assignedTo);

        // Calculate which time slots this assignment spans
        $affectedSlots = [];
        $spanCount = 0;

        foreach ($timeSlots as $index => $timeSlot) {
            $slotStart = $timeSlot->copy();
            $slotEnd = $timeSlot->copy()->addMinutes(5);

            // Check if this slot overlaps with the assignment
            if ($slotStart->lt($assignedTo) && $slotEnd->gt($assignedFrom)) {
                $affectedSlots[] = $index;
                $spanCount++;
            }
        }

        if (empty($affectedSlots)) {
            return;
        }

        // Create assignment data
        $callsign = $flight->booking->callsign ?? 'Unknown';
        if ($flight->booking->user_id) {
            $callsign .= ' - ' . $flight->booking->user_id;
        }

        $assignmentData = [
            'flight' => $flight,
            'type' => $type,
            'colspan' => $spanCount,
            'callsign' => $callsign,
            'aircraft_type' => $flight->booking->acType ?? 'Unknown',
            'relevant_airport' => $type === 'departure' ?
                ($flight->airportArr->name ?? 'Unknown') :
                ($flight->airportDep->name ?? 'Unknown'),
            'relevant_airport_icao' => $type === 'departure' ?
                ($flight->airportArr->icao ?? null) :
                ($flight->airportDep->icao ?? null),
            'time_from' => $assignedFrom->format('H:i'),
            'time_to' => $assignedTo->format('H:i')
        ];

        // Add flight to every time slot it occupies, but only with colspan on the first occurrence
        foreach ($affectedSlots as $slotIndex => $timeSlotIndex) {
            $timeSlot = $timeSlots[$timeSlotIndex];
            $timeKey = $timeSlot->format('Y-m-d H:i');

            if (isset($matrix[$bayId][$timeKey])) {
                // Check if this flight is already in this time slot
                $alreadyExists = false;
                foreach ($matrix[$bayId][$timeKey] as $existing) {
                    if (isset($existing['flight']) &&
                        $existing['flight']->id === $flight->id &&
                        $existing['type'] === $type) {
                        $alreadyExists = true;
                        break;
                    }
                }

                if (!$alreadyExists) {
                    $slotData = $assignmentData;
                    // Only apply colspan to the first slot, others get colspan=1
                    if ($slotIndex > 0) {
                        $slotData['colspan'] = 1;
                    }
                    $matrix[$bayId][$timeKey][] = $slotData;
                }
            }
        }
    }

    public function getFlightDetails(Event $event, Flight $flight, Request $request): JsonResponse
    {
        // Ensure this is a Real Flight Ops event
        if ($event->event_type_id !== EventType::REALFLIGHTOPS->value) {
            return response()->json(['error' => 'Bay management is only available for Real Flight Ops events.'], 403);
        }

        // Verify the flight belongs to this event
        if ($flight->booking->event_id !== $event->id) {
            return response()->json(['error' => 'Flight not found for this event.'], 404);
        }

        $assignmentType = $request->get('assignment_type', 'departure');

        // Load relationships
        $flight->load(['booking.user', 'airportDep', 'airportArr', 'depBay', 'arrBay']);

        // Get available bays for the airport
        $cachedDataService = new CachedDataService();
        $bays = $cachedDataService->getSortedBays($event->dep);

        // Generate the modal content HTML
        $html = view('admin.bay-management.flight-details-modal', compact(
            'flight',
            'event',
            'assignmentType',
            'bays'
        ))->render();

        return response()->json(['html' => $html]);
    }

    public function updateFlightDetails(Event $event, Flight $flight, Request $request): JsonResponse
    {
        // Ensure this is a Real Flight Ops event
        if ($event->event_type_id !== EventType::REALFLIGHTOPS->value) {
            return response()->json(['error' => 'Bay management is only available for Real Flight Ops events.'], 403);
        }

        // Verify the flight belongs to this event
        if ($flight->booking->event_id !== $event->id) {
            return response()->json(['error' => 'Flight not found for this event.'], 404);
        }

        $assignmentType = $request->get('assignment_type', 'departure');

        // Validate the request
        $rules = [];
        $data = [];

        if ($assignmentType === 'departure') {
            $rules = [
                'dep_bay' => 'nullable|exists:bays,id',
                'dep_bay_assigned_from' => 'nullable|date',
                'dep_bay_assigned_to' => 'nullable|date|after:dep_bay_assigned_from',
            ];

            $data = [
                'dep_bay' => $request->get('dep_bay') ?: null,
                'dep_bay_assigned_from' => $request->get('dep_bay_assigned_from') ?
                    Carbon::parse($request->get('dep_bay_assigned_from')) : null,
                'dep_bay_assigned_to' => $request->get('dep_bay_assigned_to') ?
                    Carbon::parse($request->get('dep_bay_assigned_to')) : null,
            ];
        } else {
            $rules = [
                'arr_bay' => 'nullable|exists:bays,id',
                'arr_bay_assigned_from' => 'nullable|date',
                'arr_bay_assigned_to' => 'nullable|date|after:arr_bay_assigned_from',
            ];

            $data = [
                'arr_bay' => $request->get('arr_bay') ?: null,
                'arr_bay_assigned_from' => $request->get('arr_bay_assigned_from') ?
                    Carbon::parse($request->get('arr_bay_assigned_from')) : null,
                'arr_bay_assigned_to' => $request->get('arr_bay_assigned_to') ?
                    Carbon::parse($request->get('arr_bay_assigned_to')) : null,
            ];
        }

        $validated = $request->validate($rules);

        try {
            // Check for overlapping bookings
            $overlapWarning = $this->checkForOverlappingBookings($flight, $assignmentType, $data, $event);

            if ($overlapWarning && !$request->get('force_save', false)) {
                return response()->json([
                    'overlap_detected' => true,
                    'overlap_message' => $overlapWarning,
                    'data' => $data
                ]);
            }

            // Update the flight
            $flight->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Flight details updated successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating flight details: ' . $e->getMessage()
            ], 500);
        }
    }

    private function checkForOverlappingBookings(Flight $currentFlight, string $assignmentType, array $data, Event $event): ?string
    {
        $bayId = null;
        $assignedFrom = null;
        $assignedTo = null;
        $bayField = null;
        $fromField = null;
        $toField = null;

        if ($assignmentType === 'departure') {
            $bayId = $data['dep_bay'] ?? null;
            $assignedFrom = $data['dep_bay_assigned_from'] ?? null;
            $assignedTo = $data['dep_bay_assigned_to'] ?? null;
            $bayField = 'dep_bay';
            $fromField = 'dep_bay_assigned_from';
            $toField = 'dep_bay_assigned_to';
        } else {
            $bayId = $data['arr_bay'] ?? null;
            $assignedFrom = $data['arr_bay_assigned_from'] ?? null;
            $assignedTo = $data['arr_bay_assigned_to'] ?? null;
            $bayField = 'arr_bay';
            $fromField = 'arr_bay_assigned_from';
            $toField = 'arr_bay_assigned_to';
        }

        // If no bay is assigned or no time range, no overlap possible
        if (!$bayId || !$assignedFrom || !$assignedTo) {
            return null;
        }

        // Find overlapping flights for the same bay and event
        $overlappingFlights = Flight::whereHas('booking', function ($query) use ($event) {
            $query->where('event_id', $event->id);
        })
        ->where('id', '!=', $currentFlight->id) // Exclude current flight
        ->where($bayField, $bayId)
        ->whereNotNull($fromField)
        ->whereNotNull($toField)
        ->where(function ($query) use ($fromField, $toField, $assignedFrom, $assignedTo) {
            // Check for overlapping time ranges
            $query->where(function ($q) use ($fromField, $toField, $assignedFrom, $assignedTo) {
                // Case 1: New booking starts before existing ends and ends after existing starts
                $q->where($fromField, '<', $assignedTo)
                  ->where($toField, '>', $assignedFrom);
            });
        })
        ->with(['booking'])
        ->get();

        if ($overlappingFlights->isEmpty()) {
            return null;
        }

        // Build warning message
        $bayName = \App\Models\Bay::find($bayId)?->name ?? 'Unknown';
        $overlappingDetails = [];

        foreach ($overlappingFlights as $overlappingFlight) {
            $callsign = $overlappingFlight->booking->callsign ?? 'Unknown';
            $fromTime = Carbon::parse($overlappingFlight->{$fromField})->format('H:i');
            $toTime = Carbon::parse($overlappingFlight->{$toField})->format('H:i');
            $overlappingDetails[] = "{$callsign} ({$fromTime}-{$toTime})";
        }

        $typeLabel = $assignmentType === 'departure' ? 'departure' : 'arrival';
        $newFromTime = Carbon::parse($assignedFrom)->format('H:i');
        $newToTime = Carbon::parse($assignedTo)->format('H:i');

        $overlappingText = implode(', ', $overlappingDetails);

        return "Bay {$bayName} {$typeLabel} assignment ({$newFromTime}-{$newToTime}) overlaps with existing booking(s): {$overlappingText}. Do you want to proceed anyway?";
    }
}
