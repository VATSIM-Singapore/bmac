<?php

namespace App\Services;

use App\Models\User;
use App\Models\Event;
use App\Models\Flight;
use App\Enums\EventType;
use App\Enums\BookingStatus;
use Illuminate\Support\Collection;

class RealFlightBookingValidator
{
    /**
     * Validate booking for Real Flight Operations events
     */
    public function validateBooking(User $user, Event $event, Flight $newFlight): ValidationResult
    {
        // Only apply to Real Flight Operations events
        if ($event->event_type_id !== EventType::REALFLIGHTOPS->value) {
            return ValidationResult::success();
        }

        // Get user's existing BOOKED bookings for this event
        $existingBookings = $user->bookings()
            ->where('event_id', $event->id)
            ->where('status', BookingStatus::BOOKED)
            ->with('flights')
            ->get();

        // Check booking count and direction restrictions
        $directionValidation = $this->validateDirectionRestrictions($existingBookings, $newFlight, $event);
        if (!$directionValidation->isSuccess()) {
            return $directionValidation;
        }

        // Check time separation
        $timeValidation = $this->validateTimeSeparation($newFlight, $existingBookings);
        if (!$timeValidation->isSuccess()) {
            return $timeValidation;
        }

        return ValidationResult::success();
    }

    /**
     * Validate direction restrictions (1 departure + 1 arrival max)
     */
    private function validateDirectionRestrictions(Collection $existingBookings, Flight $newFlight, Event $event): ValidationResult
    {
        $newDirection = $this->getFlightDirection($newFlight, $event);

        if ($newDirection === 'other') {
            return ValidationResult::error(
                "This flight doesn't match the event's departure or arrival airports. " .
                "Please select a flight from {$event->airportDep->icao} or to {$event->airportArr->icao}."
            );
        }

        // Count existing bookings by direction
        $departureCount = 0;
        $arrivalCount = 0;

        foreach ($existingBookings as $booking) {
            $flight = $booking->flights->first();
            if (!$flight) {
                continue;
            }

            $direction = $this->getFlightDirection($flight, $event);
            if ($direction === 'departure') {
                $departureCount++;
            } elseif ($direction === 'arrival') {
                $arrivalCount++;
            }
        }

        // Check if user already has max bookings
        if ($departureCount + $arrivalCount >= 2) {
            return ValidationResult::error(
                "You can only book up to 2 flights for this event: 1 departure and 1 arrival. " .
                "You currently have " . ($departureCount + $arrivalCount) . " bookings."
            );
        }

        // Check if user already has this direction
        if ($newDirection === 'departure' && $departureCount > 0) {
            return ValidationResult::error(
                "You already have a departure booking from {$event->airportDep->icao}. " .
                "You can only book 1 departure and 1 arrival for this event."
            );
        }

        if ($newDirection === 'arrival' && $arrivalCount > 0) {
            return ValidationResult::error(
                "You already have an arrival booking to {$event->airportArr->icao}. " .
                "You can only book 1 departure and 1 arrival for this event."
            );
        }

        return ValidationResult::success();
    }

    /**
     * Validate time separation (20 minutes minimum between any two flights)
     * Simple rule: Any two flights must have at least 20 minutes gap between them
     */
    private function validateTimeSeparation(Flight $newFlight, Collection $existingBookings): ValidationResult
    {
        // Ensure new flight has required time fields
        if (!$newFlight->ctot || !$newFlight->eta) {
            return ValidationResult::success();
        }

        foreach ($existingBookings as $existingBooking) {
            $existingFlight = $existingBooking->flights->first();
            if (!$existingFlight || !$existingFlight->ctot || !$existingFlight->eta) {
                continue;
            }

            // Determine which flight is earlier and which is later
            $earlierFlight = null;
            $laterFlight = null;

            if ($existingFlight->ctot < $newFlight->ctot) {
                $earlierFlight = $existingFlight;
                $laterFlight = $newFlight;
            } else {
                $earlierFlight = $newFlight;
                $laterFlight = $existingFlight;
            }

            // Check if later flight departs before earlier flight lands (overlap)
            if ($laterFlight->ctot < $earlierFlight->eta) {
                return ValidationResult::error(
                    "Your flights overlap in time. Flight departing at {$laterFlight->formatted_ctot} " .
                    "starts before your other flight lands at {$earlierFlight->formatted_eta}."
                );
            }

            // Check 20-minute minimum separation
            $timeDiff = $laterFlight->ctot->diffInMinutes($earlierFlight->eta);
            if ($timeDiff < 20) {
                return ValidationResult::error(
                    "Your flights must be separated by at least 20 minutes. " .
                    "Your flight landing at {$earlierFlight->formatted_eta} is too close to " .
                    "your next flight departing at {$laterFlight->formatted_ctot} (only {$timeDiff} minutes apart)."
                );
            }
        }

        return ValidationResult::success();
    }

    /**
     * Determine flight direction relative to event airports
     */
    private function getFlightDirection(Flight $flight, Event $event): string
    {
        if ($flight->dep == $event->dep) {
            return 'departure'; // Flying FROM event's departure airport
        } elseif ($flight->arr == $event->arr) {
            return 'arrival'; // Flying TO event's arrival airport
        }
        return 'other'; // Not relevant to this event
    }
}
