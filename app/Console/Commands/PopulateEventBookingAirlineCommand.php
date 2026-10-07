<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Booking;
use App\Models\Airline;
use Illuminate\Console\Command;

class PopulateEventBookingAirlineCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event:populate-booking-airline {eventSlug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate airline_id for bookings in an event based on callsign ICAO codes';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $eventSlug = $this->argument('eventSlug');

        // Find the event by slug
        $event = Event::where('slug', $eventSlug)->first();

        if (!$event) {
            $this->error("Event with slug '{$eventSlug}' not found.");
            return Command::FAILURE;
        }

        $this->info("Processing bookings for event: {$event->name}");

        // Get all bookings for this event that have callsigns but no airline assigned
        $bookings = $event->bookings()
            ->whereNotNull('callsign')
            ->where('callsign', '!=', '')
            ->whereNull('airline_id')
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No bookings found that need airline assignment.');
            return Command::SUCCESS;
        }

        $this->info("Found {$bookings->count()} bookings to process.");

        // Cache airlines for performance
        $airlines = Airline::all()->keyBy('icao');

        $processed = 0;
        $updated = 0;
        $failed = [];

        $this->withProgressBar($bookings, function ($booking) use ($airlines, &$processed, &$updated, &$failed) {
            $processed++;

            // Extract first 3 characters from callsign as ICAO
            $icaoCode = strtoupper(substr($booking->callsign, 0, 3));

            // Find matching airline
            if ($airlines->has($icaoCode)) {
                $airline = $airlines->get($icaoCode);

                // Update booking with airline_id
                $booking->update(['airline_id' => $airline->id]);
                $updated++;
            } else {
                // Track failed matches
                $failed[] = [
                    'booking_id' => $booking->id,
                    'callsign' => $booking->callsign,
                    'icao_extracted' => $icaoCode,
                ];
            }
        });

        $this->newLine(2);

        // Summary
        $this->info("Processing completed!");
        $this->info("Total processed: {$processed}");
        $this->info("Successfully updated: {$updated}");
        $this->info("Failed matches: " . count($failed));

        // Show failed matches if any
        if (!empty($failed)) {
            $this->newLine();
            $this->warn("The following bookings could not be matched with airlines:");

            $headers = ['Booking ID', 'Callsign', 'Extracted ICAO'];
            $rows = array_map(function ($item) {
                return [$item['booking_id'], $item['callsign'], $item['icao_extracted']];
            }, $failed);

            $this->table($headers, $rows);
        }

        return Command::SUCCESS;
    }
}
