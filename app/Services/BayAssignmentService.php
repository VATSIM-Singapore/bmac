<?php

namespace App\Services;

use App\Models\Flight;
use App\Models\Event;
use Carbon\Carbon;

class BayAssignmentService
{
    /**
     * Compute bay assignment times based on flight timing
     *
     * @param Flight $flight
     * @param Event $event
     * @return array
     */
    public function computeBayAssignmentTimes(Flight $flight, Event $event): array
    {
        $assignments = [];

        // For arrival bay (if bay is assigned and ETA is available)
        if ($flight->arr_bay && $flight->eta) {
            $eta = Carbon::parse($flight->eta);
            $assignments['arr_bay_assigned_from'] = $eta->copy()->subMinutes(20);
            $assignments['arr_bay_assigned_to'] = $eta->copy()->addMinutes(15);
        } else {
            $assignments['arr_bay_assigned_from'] = null;
            $assignments['arr_bay_assigned_to'] = null;
        }

        // For departure bay (if bay is assigned and CTOT is available)
        if ($flight->dep_bay && $flight->ctot) {
            $ctot = Carbon::parse($flight->ctot);
            $assignments['dep_bay_assigned_from'] = $ctot->copy()->subMinutes(20);
            $assignments['dep_bay_assigned_to'] = $ctot; // actual CTOT
        } else {
            $assignments['dep_bay_assigned_from'] = null;
            $assignments['dep_bay_assigned_to'] = null;
        }

        return $assignments;
    }

    /**
     * Apply bay assignments to a flight
     *
     * @param Flight $flight
     * @param Event $event
     * @param array $bayData
     * @return void
     */
    public function applyBayAssignments(Flight $flight, Event $event, array $bayData): void
    {
        // Set bay assignments if provided
        if (array_key_exists('dep_bay', $bayData)) {
            $flight->dep_bay = !empty($bayData['dep_bay']) ? $bayData['dep_bay'] : null;
        }

        if (array_key_exists('arr_bay', $bayData)) {
            $flight->arr_bay = !empty($bayData['arr_bay']) ? $bayData['arr_bay'] : null;
        }

        // Compute and set assignment times
        $assignmentTimes = $this->computeBayAssignmentTimes($flight, $event);

        $flight->dep_bay_assigned_from = $assignmentTimes['dep_bay_assigned_from'];
        $flight->dep_bay_assigned_to = $assignmentTimes['dep_bay_assigned_to'];
        $flight->arr_bay_assigned_from = $assignmentTimes['arr_bay_assigned_from'];
        $flight->arr_bay_assigned_to = $assignmentTimes['arr_bay_assigned_to'];
    }
}
