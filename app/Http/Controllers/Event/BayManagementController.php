<?php

namespace App\Http\Controllers\Event;

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

        $bayUsage = $this->buildBayUsage($bays, $flights, $timeSlots, $event);

        return view('event.bay-management.index', compact(
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
            $times = [
                $flight->dep_bay_assigned_from,
                $flight->dep_bay_assigned_to,
                $flight->arr_bay_assigned_from,
                $flight->arr_bay_assigned_to
            ];

            foreach ($times as $time) {
                if ($time) {
                    $parsedTime = Carbon::parse($time);
                    if ($parsedTime->lt($startTime)) {
                        $startTime = $parsedTime;
                    }
                    if ($parsedTime->gt($endTime)) {
                        $endTime = $parsedTime;
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
    private function buildBayUsage($bays, $flights, $timeSlots, $event)
    {
        // Use sparse matrix - only store non-empty slots
        $matrix = [];
        $bayIds = $bays->pluck('id')->toArray();

        // Pre-allocate bay arrays
        foreach ($bayIds as $bayId) {
            $matrix[$bayId] = [];
        }

        // Process flights
        foreach ($flights as $flight) {
            $this->addFlightToMatrix($matrix, $flight, $timeSlots, $event, 'departure');
            $this->addFlightToMatrix($matrix, $flight, $timeSlots, $event, 'arrival');
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

        // Get available bays for the airport
        $cachedDataService = new CachedDataService();
        $bays = $cachedDataService->getSortedBays($event->dep);

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
}
