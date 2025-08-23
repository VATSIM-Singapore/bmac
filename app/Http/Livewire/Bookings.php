<?php

namespace App\Http\Livewire;

use App\Models\Event;
use Livewire\Component;
use App\Enums\BookingStatus;

class Bookings extends Component
{
    public Event $event;
    public int $refreshInSeconds = 0;
    public $bookings;
    public ?string $filter = null;
    public int $total = 0;
    public int $booked = 0;

    public function filter($filter): void
    {
        $this->filter = strtolower($filter);
    }

    public function mount(): void
    {
        // Only enable polling if event is 'active'
        if (now()->between($this->event->startBooking, $this->event->endEvent)) {
            $this->refreshInSeconds = 15;
        }
    }

    public function render()
    {
        $filter = $this->filter;
        // @TODO Check should actually be in a policy
        if ($this->event->is_online || auth()->check() && auth()->user()->isAdmin) {
            $this->bookings = $this->event->bookings()
            ->with([
                'event',
                'user',
                'airline',
                'flights' => function ($query) use ($filter) {
                    switch ($filter) {
                        case 'departures':
                            $query->where('dep', $this->event->dep);
                            break;
                        case 'arrivals':
                            $query->where('arr', $this->event->arr);
                            break;
                    }
                },
                'flights.airportDep',
                'flights.airportArr',
            ])
            ->withCount('flights')
            ->get();
        } else {
            abort_unless(auth()->check() && auth()->user()->isAdmin, 404);
        }

        // Sort bookings by flight times based on filter
        if ($filter === 'departures') {
            $this->bookings = $this->bookings->sortBy(function ($booking) {
                $flight = $booking->flights->first();
                return $flight && $flight->ctot ? $flight->ctot->timestamp : PHP_INT_MAX;
            });
        } elseif ($filter === 'arrivals') {
            $this->bookings = $this->bookings->sortBy(function ($booking) {
                $flight = $booking->flights->first();
                return $flight && $flight->eta ? $flight->eta->timestamp : PHP_INT_MAX;
            });
        } else {
            // Default sorting: first by CTOT, then by ETA
            $this->bookings = $this->bookings->sortBy(function ($booking) {
                $flight = $booking->flights->first();
                if (!$flight) {
                    return PHP_INT_MAX;
                }

                // Use CTOT if available, otherwise ETA, otherwise max value
                if ($flight->ctot) {
                    return $flight->ctot->timestamp;
                } elseif ($flight->eta) {
                    return $flight->eta->timestamp;
                }
                return PHP_INT_MAX;
            });
        }

        $this->booked = $this->bookings->where('status', BookingStatus::BOOKED)->count();

        $this->total = $this->bookings->count();

        // https://github.com/TomasVotruba/bladestan/issues/65#issuecomment-1582383622
        return view('livewire.bookings', [
            'event' => $this->event,
            'refreshInSeconds' => $this->refreshInSeconds,
            'bookings' => $this->bookings,
            'filter' => $this->filter,
            'total' => $this->total,
            'booked' => $this->booked,
        ]);
    }
}
