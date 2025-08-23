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
    public function validateBooking(User $user, Event $event, Flight $newFlight, bool $isAdmin = false): ValidationResult
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

        // Admin override confirmation
        if ($isAdmin) {
            return ValidationResult::successWithConfirmation(
                'Admin override: Bypassing Real Flight Operations booking restrictions.'
            );
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
     * Validate time separation (30 minutes minimum between ETA and CTOT)
     */
    private function validateTimeSeparation(Flight $newFlight, Collection $existingBookings): ValidationResult
    {
        foreach ($existingBookings as $existingBooking) {
            $existingFlight = $existingBooking->flights->first();
            if (!$existingFlight) {
                continue;
            }

            // Check existing flight ETA vs new flight CTOT (new flight should depart after existing flight arrives)
            if ($existingFlight->eta && $newFlight->ctot) {
                // New flight CTOT should be after existing flight ETA
                if ($newFlight->ctot <= $existingFlight->eta) {
                    return ValidationResult::error(
                        "Your departure time ({$newFlight->formatted_ctot}) must be after your arrival time ({$existingFlight->formatted_eta})."
                    );
                }

                // Check 30 minutes minimum separation
                $timeDiff = $newFlight->ctot->diffInMinutes($existingFlight->eta);
                if ($timeDiff < 30) {
                    return ValidationResult::error(
                        "Your flights must be separated by at least 30 minutes. " .
                        "Your arrival at {$existingFlight->formatted_eta} is too close to your departure at {$newFlight->formatted_ctot}."
                    );
                }
            }

            // Check new flight ETA vs existing flight CTOT (existing flight should depart after new flight arrives)
            if ($newFlight->eta && $existingFlight->ctot) {
                // Existing flight CTOT should be after new flight ETA
                if ($existingFlight->ctot <= $newFlight->eta) {
                    return ValidationResult::error(
                        "Your departure time ({$existingFlight->formatted_ctot}) must be after your arrival time ({$newFlight->formatted_eta})."
                    );
                }

                // Check 30 minutes minimum separation
                $timeDiff = $existingFlight->ctot->diffInMinutes($newFlight->eta);
                if ($timeDiff < 30) {
                    return ValidationResult::error(
                        "Your flights must be separated by at least 30 minutes. " .
                        "Your arrival at {$newFlight->formatted_eta} is too close to your departure at {$existingFlight->formatted_ctot}."
                    );
                }
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
