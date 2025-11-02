<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\AdhocFlight;
use App\Models\Event;
use App\Models\Bay;
use App\Models\Flight;
use App\Models\BayBlocking;
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

        // Get blocked bays for this event
        $blockedBayIds = BayBlocking::where('event_id', $event->id)->pluck('bay_id')->toArray();

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

        // Get all ad hoc flights for this event that have bay assignments
        $adhocFlights = AdhocFlight::where('event_id', $event->id)
            ->with(['bay', 'airportDep', 'airportArr'])
            ->whereNotNull('bay_id')
            ->get();

        // Determine time range from bay assignments (including ad hoc flights)
        $timeRange = $this->calculateTimeRange($event, $flights, $adhocFlights);

        // Generate 5-minute time slots
        $timeSlots = $this->generateTimeSlots($timeRange['start'], $timeRange['end']);

        $bayUsage = $this->buildBayUsage($bays, $flights, $timeSlots, $event, $adhocFlights);

        return view('event.bay-management.index', compact(
            'event',
            'bays',
            'timeSlots',
            'bayUsage',
            'timeRange',
            'blockedBayIds'
        ));
    }

    private function calculateTimeRange(Event $event, $flights, $adhocFlights = null)
    {
        // Start with event times with 1-hour buffer
        $startTime = $event->startEvent->copy()->subHour();
        $endTime = $event->endEvent->copy()->addHour();

        // Set reasonable bounds to prevent extreme time ranges
        $minTime = $event->startEvent->copy()->subDay(); // 1 day before event
        $maxTime = $event->endEvent->copy()->addDay();   // 1 day after event

        // Expand based on actual bay assignments from regular flights
        foreach ($flights as $flight) {
            $times = [
                $flight->dep_bay_assigned_from,
                $flight->dep_bay_assigned_to,
                $flight->arr_bay_assigned_from,
                $flight->arr_bay_assigned_to
            ];

            foreach ($times as $time) {
                if ($time) {
                    $parsedTime = Carbon::parse($time);
                    // Only consider times within reasonable bounds
                    if ($parsedTime->gte($minTime) && $parsedTime->lte($maxTime)) {
                        if ($parsedTime->lt($startTime)) {
                            $startTime = $parsedTime;
                        }
                        if ($parsedTime->gt($endTime)) {
                            $endTime = $parsedTime;
                        }
                    }
                }
            }
        }

        // Expand based on actual bay assignments from ad hoc flights
        if ($adhocFlights) {
            foreach ($adhocFlights as $adhocFlight) {
                $times = [
                    $adhocFlight->bay_assigned_from,
                    $adhocFlight->bay_assigned_to
                ];

                foreach ($times as $time) {
                    if ($time) {
                        // $time is already a Carbon instance due to model casts
                        $parsedTime = $time instanceof Carbon ? $time : Carbon::parse($time);
                        // Only consider times within reasonable bounds
                        if ($parsedTime->gte($minTime) && $parsedTime->lte($maxTime)) {
                            if ($parsedTime->lt($startTime)) {
                                $startTime = $parsedTime;
                            }
                            if ($parsedTime->gt($endTime)) {
                                $endTime = $parsedTime;
                            }
                        }
                    }
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
        $current = $start->copy()->startOfHour();

        // Round to nearest 5-minute interval
        $minutes = $current->minute;
        $roundedMinutes = floor($minutes / 5) * 5;
        $current->minute($roundedMinutes)->second(0);

        // Pre-allocate array size for better memory efficiency
        $totalSlots = (int) ceil($end->diffInMinutes($current) / 5);
        $slots = array_fill(0, $totalSlots, null);

        for ($i = 0; $i < $totalSlots; $i++) {
            $slots[$i] = $current->copy();
            $current->addMinutes(5);
        }

        return $slots;
    }

    /**
     * Build bay usage data structure using sparse matrix approach
     */
    private function buildBayUsage($bays, $flights, $timeSlots, $event, $adhocFlights = null)
    {
        // Use sparse matrix - only store non-empty slots
        $matrix = [];
        $bayIds = $bays->pluck('id')->toArray();

        // Pre-allocate bay arrays
        foreach ($bayIds as $bayId) {
            $matrix[$bayId] = [];
        }

        // Process regular flights
        foreach ($flights as $flight) {
            $this->addFlightToMatrix($matrix, $flight, $timeSlots, $event, 'departure');
            $this->addFlightToMatrix($matrix, $flight, $timeSlots, $event, 'arrival');
        }

        // Process ad hoc flights
        if ($adhocFlights) {
            foreach ($adhocFlights as $adhocFlight) {
                $this->addAdhocFlightToMatrix($matrix, $adhocFlight, $timeSlots, $event);
            }
        }

        // Organize data for view display - group by row for each bay
        foreach ($bayIds as $bayId) {
            $matrix[$bayId] = $this->organizeBayDataForView($matrix[$bayId], $timeSlots);
        }

        return $matrix;
    }

    /**
     * Add flight to matrix using interval-based approach
     */
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

        // Use binary search to find affected time slots
        $affectedSlots = $this->findAffectedTimeSlots($timeSlots, $assignedFrom, $assignedTo);

        if (empty($affectedSlots)) {
            return;
        }

        // Create assignment data once
        $callsign = $flight->booking->callsign ?? 'Unknown';
        if ($flight->booking->user_id) {
            $callsign .= ' - ' . $flight->booking->user_id;
        }

        $assignmentData = [
            'flight' => $flight,
            'type' => $type,
            'colspan' => count($affectedSlots),
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

        // Add flight to affected time slots
        foreach ($affectedSlots as $index => $timeSlotIndex) {
            $timeSlot = $timeSlots[$timeSlotIndex];
            $timeKey = $timeSlot->format('Y-m-d H:i');

            if (!isset($matrix[$bayId][$timeKey])) {
                $matrix[$bayId][$timeKey] = [];
            }

            // Check for duplicates
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
                // Only apply colspan to the first slot
                if ($index > 0) {
                    $slotData['colspan'] = 1;
                }
                $matrix[$bayId][$timeKey][] = $slotData;
            }
        }
    }

    /**
     * Add ad hoc flight to matrix using interval-based approach
     */
    private function addAdhocFlightToMatrix(&$matrix, $adhocFlight, $timeSlots, $event)
    {
        if (!$adhocFlight->bay_id || !$adhocFlight->bay_assigned_from || !$adhocFlight->bay_assigned_to) {
            return;
        }

        $bayId = $adhocFlight->bay_id;
        // These are already Carbon instances due to model casts
        $assignedFrom = $adhocFlight->bay_assigned_from instanceof Carbon ? $adhocFlight->bay_assigned_from : Carbon::parse($adhocFlight->bay_assigned_from);
        $assignedTo = $adhocFlight->bay_assigned_to instanceof Carbon ? $adhocFlight->bay_assigned_to : Carbon::parse($adhocFlight->bay_assigned_to);

        // Use binary search to find affected time slots
        $affectedSlots = $this->findAffectedTimeSlots($timeSlots, $assignedFrom, $assignedTo);

        if (empty($affectedSlots)) {
            return;
        }

        // Create assignment data once
        $callsign = $adhocFlight->callsign ?? 'Unknown';
        $callsign .= ' (Ad Hoc)';

        // Determine the relevant airport and flight type based on which one matches the event
        $relevantAirport = 'Unknown';
        $relevantAirportIcao = null;
        $flightType = 'departure'; // Default to departure

        if ($adhocFlight->dep == $event->dep) {
            $relevantAirport = $adhocFlight->airportArr->name ?? 'Unknown';
            $relevantAirportIcao = $adhocFlight->airportArr->icao ?? null;
            $flightType = 'departure';
        } elseif ($adhocFlight->arr == $event->arr) {
            $relevantAirport = $adhocFlight->airportDep->name ?? 'Unknown';
            $relevantAirportIcao = $adhocFlight->airportDep->icao ?? null;
            $flightType = 'arrival';
        }

        $assignmentData = [
            'flight' => $adhocFlight,
            'type' => 'adhoc',
            'flight_type' => $flightType,
            'colspan' => count($affectedSlots),
            'callsign' => $callsign,
            'aircraft_type' => $adhocFlight->acType ?? 'Unknown',
            'relevant_airport' => $relevantAirport,
            'relevant_airport_icao' => $relevantAirportIcao,
            'time_from' => $assignedFrom->format('H:i'),
            'time_to' => $assignedTo->format('H:i')
        ];

        // Add flight to affected time slots
        foreach ($affectedSlots as $index => $timeSlotIndex) {
            $timeSlot = $timeSlots[$timeSlotIndex];
            $timeKey = $timeSlot->format('Y-m-d H:i');

            if (!isset($matrix[$bayId][$timeKey])) {
                $matrix[$bayId][$timeKey] = [];
            }

            // Check for duplicates
            $alreadyExists = false;

            foreach ($matrix[$bayId][$timeKey] as $existing) {
                if (isset($existing['flight']) &&
                    $existing['flight']->id === $adhocFlight->id &&
                    $existing['type'] === 'adhoc') {
                    $alreadyExists = true;
                    break;
                }
            }

            if (!$alreadyExists) {
                $slotData = $assignmentData;
                // Only apply colspan to the first slot
                if ($index > 0) {
                    $slotData['colspan'] = 1;
                }
                $matrix[$bayId][$timeKey][] = $slotData;
            }
        }
    }

    /**
     * Find affected time slots using binary search
     */
    private function findAffectedTimeSlots($timeSlots, Carbon $start, Carbon $end)
    {
        $affectedSlots = [];
        $count = count($timeSlots);

        // Binary search for start index
        $startIndex = $this->binarySearchTimeSlot($timeSlots, $start, 0, $count - 1);

        // Binary search for end index
        $endIndex = $this->binarySearchTimeSlot($timeSlots, $end, 0, $count - 1);

        // Adjust indices to ensure we capture all overlapping slots
        if ($startIndex > 0) {
            $startIndex--;
        }
        if ($endIndex < $count - 1) {
            $endIndex++;
        }

        // Collect all affected slots
        for ($i = $startIndex; $i <= $endIndex; $i++) {
            if ($i >= 0 && $i < $count) {
                $timeSlot = $timeSlots[$i];
                $slotStart = $timeSlot->copy();
                $slotEnd = $timeSlot->copy()->addMinutes(5);

                // Check if this slot overlaps with the assignment
                if ($slotStart->lt($end) && $slotEnd->gt($start)) {
                    $affectedSlots[] = $i; // Just store the index, not the full data
                }
            }
        }

        return $affectedSlots;
    }

    /**
     * Binary search to find the closest time slot index
     */
    private function binarySearchTimeSlot($timeSlots, Carbon $target, $left, $right)
    {
        if ($left > $right) {
            return $left;
        }

        $mid = (int) (($left + $right) / 2);
        $midTime = $timeSlots[$mid];

        if ($midTime->eq($target)) {
            return $mid;
        }

        if ($midTime->lt($target)) {
            return $this->binarySearchTimeSlot($timeSlots, $target, $mid + 1, $right);
        }

        return $this->binarySearchTimeSlot($timeSlots, $target, $left, $mid - 1);
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

        // Get available bays for the airport with blocked status
        $cachedDataService = new CachedDataService();
        $bays = $cachedDataService->getBaysForSelect($event->dep, $event->id);

        // Generate the modal content HTML
        $html = view('event.bay-management.flight-details-modal', compact(
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

        $request->validate($rules);

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

    /**
     * Overlap detection using interval-based approach
     * Checks for overlaps between ALL combinations of departure and arrival assignments on the same bay
     */
    private function checkForOverlappingBookings(Flight $currentFlight, string $assignmentType, array $data, Event $event): ?string
    {
        if ($assignmentType === 'departure') {
            $bayId = $data['dep_bay'] ?? null;
            $assignedFrom = $data['dep_bay_assigned_from'] ?? null;
            $assignedTo = $data['dep_bay_assigned_to'] ?? null;
        } else {
            $bayId = $data['arr_bay'] ?? null;
            $assignedFrom = $data['arr_bay_assigned_from'] ?? null;
            $assignedTo = $data['arr_bay_assigned_to'] ?? null;
        }

        // If no bay is assigned or no time range, no overlap possible
        if (!$bayId || !$assignedFrom || !$assignedTo) {
            return null;
        }

        // Check for overlaps with BOTH departure and arrival assignments on the same bay
        $overlappingFlights = Flight::whereHas('booking', function ($query) use ($event) {
            $query->where('event_id', $event->id);
        })
            ->where('id', '!=', $currentFlight->id)
            ->where(function ($query) use ($bayId) {
                // Check both departure and arrival bay assignments
                $query->where('dep_bay', $bayId)
                    ->orWhere('arr_bay', $bayId);
            })
            ->where(function ($query) use ($assignedFrom, $assignedTo) {
                // Check for time overlaps with departure assignments
                $query->where(function ($subQuery) use ($assignedFrom, $assignedTo) {
                    $subQuery->whereNotNull('dep_bay_assigned_from')
                        ->whereNotNull('dep_bay_assigned_to')
                        ->where('dep_bay_assigned_from', '<', $assignedTo)
                        ->where('dep_bay_assigned_to', '>', $assignedFrom);
                })
                // Check for time overlaps with arrival assignments
                ->orWhere(function ($subQuery) use ($assignedFrom, $assignedTo) {
                    $subQuery->whereNotNull('arr_bay_assigned_from')
                        ->whereNotNull('arr_bay_assigned_to')
                        ->where('arr_bay_assigned_from', '<', $assignedTo)
                        ->where('arr_bay_assigned_to', '>', $assignedFrom);
                });
            })
            ->with(['booking'])
            ->get();

        if ($overlappingFlights->isEmpty()) {
            return null;
        }

        $bayName = \App\Models\Bay::find($bayId)?->name ?? 'Unknown';
        $overlappingDetails = [];

        foreach ($overlappingFlights as $overlappingFlight) {
            $callsign = $overlappingFlight->booking->callsign ?? 'Unknown';

            // Check which type of assignment overlaps and get the times
            $overlapType = '';
            $fromTime = '';
            $toTime = '';

            // Check if departure assignment overlaps
            if ($overlappingFlight->dep_bay == $bayId &&
                $overlappingFlight->dep_bay_assigned_from &&
                $overlappingFlight->dep_bay_assigned_to &&
                $overlappingFlight->dep_bay_assigned_from < $assignedTo &&
                $overlappingFlight->dep_bay_assigned_to > $assignedFrom) {
                $overlapType = 'departure';
                $fromTime = Carbon::parse($overlappingFlight->dep_bay_assigned_from)->format('H:i');
                $toTime = Carbon::parse($overlappingFlight->dep_bay_assigned_to)->format('H:i');
            }
            // Check if arrival assignment overlaps
            elseif ($overlappingFlight->arr_bay == $bayId &&
                    $overlappingFlight->arr_bay_assigned_from &&
                    $overlappingFlight->arr_bay_assigned_to &&
                    $overlappingFlight->arr_bay_assigned_from < $assignedTo &&
                    $overlappingFlight->arr_bay_assigned_to > $assignedFrom) {
                $overlapType = 'arrival';
                $fromTime = Carbon::parse($overlappingFlight->arr_bay_assigned_from)->format('H:i');
                $toTime = Carbon::parse($overlappingFlight->arr_bay_assigned_to)->format('H:i');
            }

            if ($overlapType) {
                $overlappingDetails[] = "{$callsign} ({$overlapType}: {$fromTime}-{$toTime})";
            }
        }

        $typeLabel = $assignmentType === 'departure' ? 'departure' : 'arrival';
        $newFromTime = Carbon::parse($assignedFrom)->format('H:i');
        $newToTime = Carbon::parse($assignedTo)->format('H:i');

        $overlappingText = implode(', ', $overlappingDetails);

        return "Bay {$bayName} {$typeLabel} assignment ({$newFromTime}-{$newToTime}) overlaps with existing booking(s): {$overlappingText}. Do you want to proceed anyway?";
    }

    /**
     * Organize bay data for view display by grouping flights into rows
     */
    private function organizeBayDataForView($bayData, $timeSlots)
    {
        $organizedData = [];

        // Safety check: ensure bayData is an array
        if (!is_array($bayData)) {
            return $organizedData;
        }

        // First, collect all unique flights for this bay
        $uniqueFlights = [];
        foreach ($bayData as $timeKey => $assignments) {
            if (is_array($assignments)) {
                foreach ($assignments as $assignment) {
                    if ($assignment && isset($assignment['flight']) && isset($assignment['type'])) {
                        $flightKey = $assignment['flight']->id . '_' . $assignment['type'];
                        if (!isset($uniqueFlights[$flightKey])) {
                            $uniqueFlights[$flightKey] = $assignment;
                        }
                    }
                }
            }
        }

        // Smart row allocation - assign flights to rows based on time conflicts
        $flightToRowMap = [];
        $rowOccupancy = []; // Track which time ranges each row occupies

        foreach ($uniqueFlights as $flightKey => $flight) {
            $assigned = false;
            $flightStart = $flight['time_from'];
            $flightEnd = $flight['time_to'];

            // Try to assign to an existing row that doesn't conflict
            foreach ($rowOccupancy as $rowIndex => $occupiedRanges) {
                $canUseRow = true;
                foreach ($occupiedRanges as $range) {
                    // Check if flight times overlap with existing range
                    if ($flightStart < $range['end'] && $flightEnd > $range['start']) {
                        $canUseRow = false;
                        break;
                    }
                }

                if ($canUseRow) {
                    $flightToRowMap[$flightKey] = $rowIndex;
                    $rowOccupancy[$rowIndex][] = ['start' => $flightStart, 'end' => $flightEnd];
                    $assigned = true;
                    break;
                }
            }

            // If no existing row works, create a new one
            if (!$assigned) {
                $newRowIndex = count($rowOccupancy);
                $flightToRowMap[$flightKey] = $newRowIndex;
                $rowOccupancy[$newRowIndex] = [['start' => $flightStart, 'end' => $flightEnd]];
            }
        }

        // Organize data by time slot and row
        foreach ($timeSlots as $timeSlot) {
            $timeKey = $timeSlot->format('Y-m-d H:i');
            $assignments = $bayData[$timeKey] ?? [];

            // Initialize row array for this time slot
            $organizedData[$timeKey] = [];

            // Place assignments in their designated rows
            if (is_array($assignments)) {
                foreach ($assignments as $assignment) {
                    if ($assignment && isset($assignment['flight']) && isset($assignment['type'])) {
                        $flightKey = $assignment['flight']->id . '_' . $assignment['type'];
                        $rowIndex = $flightToRowMap[$flightKey] ?? 0;

                        // Ensure the row exists
                        while (count($organizedData[$timeKey]) <= $rowIndex) {
                            $organizedData[$timeKey][] = null;
                        }

                        $organizedData[$timeKey][$rowIndex] = $assignment;
                    }
                }
            }
        }

        return $organizedData;
    }

    /**
     * Get blocked bays for the event
     */
    public function getBlockedBays(Event $event): JsonResponse
    {
        // Ensure this is a Real Flight Ops event
        if ($event->event_type_id !== EventType::REALFLIGHTOPS->value) {
            return response()->json(['error' => 'Bay management is only available for Real Flight Ops events.'], 403);
        }

        $blockedBays = BayBlocking::where('event_id', $event->id)
            ->with('bay')
            ->get();

        return response()->json(['blockedBays' => $blockedBays]);
    }

    /**
     * Block a bay for the event
     */
    public function blockBay(Event $event, Request $request): JsonResponse
    {
        // Ensure this is a Real Flight Ops event
        if ($event->event_type_id !== EventType::REALFLIGHTOPS->value) {
            return response()->json(['error' => 'Bay management is only available for Real Flight Ops events.'], 403);
        }

        $request->validate([
            'bay_id' => 'required|exists:bays,id'
        ]);

        $bayId = $request->get('bay_id');

        // Check if bay is already blocked for this event
        $existingBlocking = BayBlocking::where('event_id', $event->id)
            ->where('bay_id', $bayId)
            ->first();

        if ($existingBlocking) {
            return response()->json([
                'success' => false,
                'message' => 'This bay is already blocked for this event.'
            ]);
        }

        // Verify the bay belongs to the event's airport
        $bay = Bay::find($bayId);
        if (!$bay || (int)$bay->airport_id !== (int)$event->dep) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid bay for this event.'
            ]);
        }

        try {
            BayBlocking::create([
                'event_id' => $event->id,
                'bay_id' => $bayId
            ]);

            return response()->json([
                'success' => true,
                'message' => "Bay {$bay->name} has been blocked successfully."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error blocking bay: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unblock a bay for the event
     */
    public function unblockBay(Event $event, Request $request): JsonResponse
    {
        // Ensure this is a Real Flight Ops event
        if ($event->event_type_id !== EventType::REALFLIGHTOPS->value) {
            return response()->json(['error' => 'Bay management is only available for Real Flight Ops events.'], 403);
        }

        $request->validate([
            'blocking_id' => 'required|exists:bay_blockings,id'
        ]);

        $blockingId = $request->get('blocking_id');

        try {
            $blocking = BayBlocking::where('id', $blockingId)
                ->where('event_id', $event->id)
                ->with('bay')
                ->first();

            if (!$blocking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bay blocking not found.'
                ]);
            }

            $bayName = $blocking->bay->name;
            $blocking->delete();

            return response()->json([
                'success' => true,
                'message' => "Bay {$bayName} has been unblocked successfully."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error unblocking bay: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new ad hoc flight
     */
    public function storeAdhocFlight(Event $event, Request $request): JsonResponse
    {
        // Ensure this is a Real Flight Ops event
        if ($event->event_type_id !== EventType::REALFLIGHTOPS->value) {
            return response()->json(['error' => 'Bay management is only available for Real Flight Ops events.'], 403);
        }

        $request->validate([
            'callsign' => 'required|string|max:255',
            'acType' => 'required|string|max:255',
            'dep' => 'nullable|exists:airports,id',
            'arr' => 'nullable|exists:airports,id',
            'std' => 'nullable|date',
            'sta' => 'nullable|date',
            'bay_id' => 'required|exists:bays,id',
            'time_slot' => 'required|string',
            'flight_type' => 'required|in:departure,arrival',
            'bay_assigned_from' => 'required|date',
            'bay_assigned_to' => 'required|date|after:bay_assigned_from',
        ]);

        // Validate that all dates are within the event date range
        $eventStart = $event->startEvent->copy()->startOfDay();
        $eventEnd = $event->endEvent->copy()->endOfDay();

        if ($request->std) {
            $stdDate = Carbon::parse($request->std);
            if ($stdDate->lt($eventStart) || $stdDate->gt($eventEnd)) {
                return response()->json([
                    'success' => false,
                    'message' => 'STD must be within the event date range (' . $event->startEvent->format('Y-m-d') . ' to ' . $event->endEvent->format('Y-m-d') . ')'
                ], 422);
            }
        }

        if ($request->sta) {
            $staDate = Carbon::parse($request->sta);
            if ($staDate->lt($eventStart) || $staDate->gt($eventEnd)) {
                return response()->json([
                    'success' => false,
                    'message' => 'STA must be within the event date range (' . $event->startEvent->format('Y-m-d') . ' to ' . $event->endEvent->format('Y-m-d') . ')'
                ], 422);
            }
        }

        $bayFromDate = Carbon::parse($request->bay_assigned_from);
        $bayToDate = Carbon::parse($request->bay_assigned_to);

        if ($bayFromDate->lt($eventStart) || $bayFromDate->gt($eventEnd) ||
            $bayToDate->lt($eventStart) || $bayToDate->gt($eventEnd)) {
            return response()->json([
                'success' => false,
                'message' => 'Bay assignment times must be within the event date range (' . $event->startEvent->format('Y-m-d') . ' to ' . $event->endEvent->format('Y-m-d') . ')'
            ], 422);
        }

        try {
            // Check for overlapping bookings
            $overlapWarning = $this->checkForAdhocOverlappingBookings($event, $request);

            if ($overlapWarning && !$request->get('force_save', false)) {
                return response()->json([
                    'overlap_detected' => true,
                    'overlap_message' => $overlapWarning,
                ]);
            }

            // Create the ad hoc flight
            $adhocFlight = AdhocFlight::create([
                'event_id' => $event->id,
                'callsign' => $request->callsign,
                'acType' => $request->acType,
                'dep' => $request->dep,
                'arr' => $request->arr,
                'std' => $request->std ? Carbon::parse($request->std) : null,
                'sta' => $request->sta ? Carbon::parse($request->sta) : null,
                'bay_id' => $request->bay_id,
                'bay_assigned_from' => Carbon::parse($request->bay_assigned_from),
                'bay_assigned_to' => Carbon::parse($request->bay_assigned_to),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ad hoc flight created successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating ad hoc flight: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ad hoc flight details
     */
    public function getAdhocFlight(Event $event, Request $request): JsonResponse
    {
        $request->validate([
            'flight_id' => 'required|exists:adhoc_flights,id'
        ]);

        try {
            $adhocFlight = AdhocFlight::with(['airportDep', 'airportArr', 'bay'])
                ->where('id', $request->flight_id)
                ->where('event_id', $event->id)
                ->first();

            if (!$adhocFlight) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ad hoc flight not found.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'flight' => [
                    'id' => $adhocFlight->id,
                    'callsign' => $adhocFlight->callsign,
                    'acType' => $adhocFlight->acType,
                    'dep' => $adhocFlight->dep,
                    'arr' => $adhocFlight->arr,
                    'dep_airport' => $adhocFlight->airportDep ? $adhocFlight->airportDep->name . ' (' . $adhocFlight->airportDep->icao . ')' : null,
                    'arr_airport' => $adhocFlight->airportArr ? $adhocFlight->airportArr->name . ' (' . $adhocFlight->airportArr->icao . ')' : null,
                    'bay_id' => $adhocFlight->bay_id,
                    'bay_name' => $adhocFlight->bay ? $adhocFlight->bay->name : null,
                    'bay_assigned_from' => $adhocFlight->bay_assigned_from,
                    'bay_assigned_to' => $adhocFlight->bay_assigned_to,
                    'std' => $adhocFlight->std,
                    'sta' => $adhocFlight->sta,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving ad hoc flight: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update ad hoc flight
     */
    public function updateAdhocFlight(Event $event, Request $request): JsonResponse
    {
        // Ensure this is a Real Flight Ops event
        if ($event->event_type_id !== EventType::REALFLIGHTOPS->value) {
            return response()->json(['error' => 'Bay management is only available for Real Flight Ops events.'], 403);
        }

        $request->validate([
            'flight_id' => 'required|exists:adhoc_flights,id',
            'callsign' => 'required|string|max:255',
            'acType' => 'required|string|max:255',
            'dep' => 'nullable|exists:airports,id',
            'arr' => 'nullable|exists:airports,id',
            'std' => 'nullable|date',
            'sta' => 'nullable|date',
            'bay_id' => 'required|exists:bays,id',
            'bay_assigned_from' => 'required|date',
            'bay_assigned_to' => 'required|date|after:bay_assigned_from',
        ]);

        // Validate that all dates are within the event date range
        $eventStart = $event->startEvent->copy()->startOfDay();
        $eventEnd = $event->endEvent->copy()->endOfDay();

        if ($request->std) {
            $stdDate = Carbon::parse($request->std);
            if ($stdDate->lt($eventStart) || $stdDate->gt($eventEnd)) {
                return response()->json([
                    'success' => false,
                    'message' => 'STD must be within the event date range (' . $event->startEvent->format('Y-m-d') . ' to ' . $event->endEvent->format('Y-m-d') . ')'
                ], 422);
            }
        }

        if ($request->sta) {
            $staDate = Carbon::parse($request->sta);
            if ($staDate->lt($eventStart) || $staDate->gt($eventEnd)) {
                return response()->json([
                    'success' => false,
                    'message' => 'STA must be within the event date range (' . $event->startEvent->format('Y-m-d') . ' to ' . $event->endEvent->format('Y-m-d') . ')'
                ], 422);
            }
        }

        $bayFromDate = Carbon::parse($request->bay_assigned_from);
        $bayToDate = Carbon::parse($request->bay_assigned_to);

        if ($bayFromDate->lt($eventStart) || $bayFromDate->gt($eventEnd) ||
            $bayToDate->lt($eventStart) || $bayToDate->gt($eventEnd)) {
            return response()->json([
                'success' => false,
                'message' => 'Bay assignment times must be within the event date range (' . $event->startEvent->format('Y-m-d') . ' to ' . $event->endEvent->format('Y-m-d') . ')'
            ], 422);
        }

        try {
            // Find the ad hoc flight
            $adhocFlight = AdhocFlight::where('event_id', $event->id)
                ->where('id', $request->flight_id)
                ->first();

            if (!$adhocFlight) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ad hoc flight not found.'
                ], 404);
            }

            // Update the ad hoc flight
            $adhocFlight->update([
                'callsign' => $request->callsign,
                'acType' => $request->acType,
                'dep' => $request->dep,
                'arr' => $request->arr,
                'std' => $request->std ? Carbon::parse($request->std) : null,
                'sta' => $request->sta ? Carbon::parse($request->sta) : null,
                'bay_id' => $request->bay_id,
                'bay_assigned_from' => Carbon::parse($request->bay_assigned_from),
                'bay_assigned_to' => Carbon::parse($request->bay_assigned_to),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ad hoc flight updated successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating ad hoc flight: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete ad hoc flight
     */
    public function deleteAdhocFlight(Event $event, Request $request): JsonResponse
    {
        $request->validate([
            'flight_id' => 'required|exists:adhoc_flights,id'
        ]);

        try {
            $adhocFlight = AdhocFlight::where('id', $request->flight_id)
                ->where('event_id', $event->id)
                ->first();

            if (!$adhocFlight) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ad hoc flight not found.'
                ], 404);
            }

            $adhocFlight->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ad hoc flight deleted successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting ad hoc flight: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check for overlapping bookings for ad hoc flights
     */
    private function checkForAdhocOverlappingBookings(Event $event, Request $request): ?string
    {
        $bayId = $request->bay_id;
        $assignedFrom = Carbon::parse($request->bay_assigned_from);
        $assignedTo = Carbon::parse($request->bay_assigned_to);
        $flightType = $request->flight_type;

        // Check for overlaps with existing flights
        $overlappingFlights = Flight::whereHas('booking', function ($query) use ($event) {
            $query->where('event_id', $event->id);
        })
            ->where(function ($query) use ($bayId) {
                $query->where('dep_bay', $bayId)
                    ->orWhere('arr_bay', $bayId);
            })
            ->where(function ($query) use ($assignedFrom, $assignedTo) {
                $query->where(function ($subQuery) use ($assignedFrom, $assignedTo) {
                    $subQuery->whereNotNull('dep_bay_assigned_from')
                        ->whereNotNull('dep_bay_assigned_to')
                        ->where('dep_bay_assigned_from', '<', $assignedTo)
                        ->where('dep_bay_assigned_to', '>', $assignedFrom);
                })
                ->orWhere(function ($subQuery) use ($assignedFrom, $assignedTo) {
                    $subQuery->whereNotNull('arr_bay_assigned_from')
                        ->whereNotNull('arr_bay_assigned_to')
                        ->where('arr_bay_assigned_from', '<', $assignedTo)
                        ->where('arr_bay_assigned_to', '>', $assignedFrom);
                });
            })
            ->with(['booking'])
            ->get();

        // Check for overlaps with existing ad hoc flights
        $overlappingAdhocFlights = AdhocFlight::where('event_id', $event->id)
            ->where('bay_id', $bayId)
            ->where(function ($query) use ($assignedFrom, $assignedTo) {
                $query->whereNotNull('bay_assigned_from')
                    ->whereNotNull('bay_assigned_to')
                    ->where('bay_assigned_from', '<', $assignedTo)
                    ->where('bay_assigned_to', '>', $assignedFrom);
            })
            ->get();

        if ($overlappingFlights->isEmpty() && $overlappingAdhocFlights->isEmpty()) {
            return null;
        }

        $bayName = Bay::find($bayId)?->name ?? 'Unknown';
        $overlappingDetails = [];

        // Process regular flight overlaps
        foreach ($overlappingFlights as $overlappingFlight) {
            $callsign = $overlappingFlight->booking->callsign ?? 'Unknown';
            $overlapType = '';
            $fromTime = '';
            $toTime = '';

            if ($overlappingFlight->dep_bay == $bayId &&
                $overlappingFlight->dep_bay_assigned_from &&
                $overlappingFlight->dep_bay_assigned_to &&
                $overlappingFlight->dep_bay_assigned_from < $assignedTo &&
                $overlappingFlight->dep_bay_assigned_to > $assignedFrom) {
                $overlapType = 'departure';
                $fromTime = Carbon::parse($overlappingFlight->dep_bay_assigned_from)->format('H:i');
                $toTime = Carbon::parse($overlappingFlight->dep_bay_assigned_to)->format('H:i');
            } elseif ($overlappingFlight->arr_bay == $bayId &&
                    $overlappingFlight->arr_bay_assigned_from &&
                    $overlappingFlight->arr_bay_assigned_to &&
                    $overlappingFlight->arr_bay_assigned_from < $assignedTo &&
                    $overlappingFlight->arr_bay_assigned_to > $assignedFrom) {
                $overlapType = 'arrival';
                $fromTime = Carbon::parse($overlappingFlight->arr_bay_assigned_from)->format('H:i');
                $toTime = Carbon::parse($overlappingFlight->arr_bay_assigned_to)->format('H:i');
            }

            if ($overlapType) {
                $overlappingDetails[] = "{$callsign} ({$overlapType}: {$fromTime}-{$toTime})";
            }
        }

        // Process ad hoc flight overlaps
        foreach ($overlappingAdhocFlights as $overlappingFlight) {
            $callsign = $overlappingFlight->callsign;
            $fromTime = $overlappingFlight->bay_assigned_from->format('H:i');
            $toTime = $overlappingFlight->bay_assigned_to->format('H:i');

            $overlappingDetails[] = "{$callsign} (Ad Hoc: {$fromTime}-{$toTime})";
        }

        $newFromTime = $assignedFrom->format('H:i');
        $newToTime = $assignedTo->format('H:i');
        $overlappingText = implode(', ', $overlappingDetails);

        return "Bay {$bayName} {$flightType} assignment ({$newFromTime}-{$newToTime}) overlaps with existing booking(s): {$overlappingText}. Do you want to proceed anyway?";
    }

    /**
     * Update flight assignment via drag and drop
     */
    public function updateFlightAssignment(Event $event, Flight $flight, Request $request): JsonResponse
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

        $request->validate($rules);

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
                'message' => 'Flight assignment updated successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating flight assignment: ' . $e->getMessage()
            ], 500);
        }
    }
}
