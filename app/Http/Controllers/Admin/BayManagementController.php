<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Bay;
use App\Models\Flight;
use App\Enums\EventType;
use Carbon\Carbon;

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
        $bays = Bay::getSortedBaysForAirport($event->dep);

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
            'time_to' => $assignedTo->format('H:i'),
        ];

        // Add to first affected slot, mark others as spanned
        foreach ($affectedSlots as $slotIndex => $timeSlotIndex) {
            $timeSlot = $timeSlots[$timeSlotIndex];
            $timeKey = $timeSlot->format('Y-m-d H:i');

            if (isset($matrix[$bayId][$timeKey])) {
                if ($slotIndex === 0) {
                    // First slot gets the full assignment data
                    $matrix[$bayId][$timeKey][] = $assignmentData;
                } else {
                    // Subsequent slots get a marker to skip rendering
                    $matrix[$bayId][$timeKey][] = ['skip' => true];
                }
            }
        }
    }
}
