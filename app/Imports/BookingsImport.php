<?php

namespace App\Imports;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\Airport;
use App\Models\Airline;
use App\Models\Booking;
use App\Services\CachedDataService;
use Maatwebsite\Excel\Concerns\ToModel;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Collection;

class BookingsImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading, WithValidation
{
    use Importable;

    private Collection $airlinesCache;
    private array $invalidAirlines = [];

    public function __construct(public Event $event)
    {
        // Load airlines cache for fast lookup
        $cachedDataService = new CachedDataService();
        $this->airlinesCache = $cachedDataService->getAirlinesForSelect();
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row): void
    {
        $editable = true;
        if (!empty($row['call_sign']) && !empty($row['aircraft_type'])) {
            $editable = false;
        }

        // Handle airline ICAO code
        $airlineId = $this->getAirlineId($row['airline'] ?? null);

        $booking = Booking::create([
            'event_id' => $this->event->id,
            'is_editable' => $editable,
            'callsign' => $row['call_sign'] ?? null,
            'acType'   => $row['aircraft_type'] ?? null,
            'airline_id' => $airlineId,
        ]);

        if ($this->event->event_type_id == EventType::MULTIFLIGHTS->value) {
            $airport1 = $this->getAirport($row['airport_1']);
            $airport2 = $this->getAirport($row['airport_2']);
            $airport3 = $this->getAirport($row['airport_3']);
            $ctot1 = $this->getTime($row['ctot_1'] ?? null);
            $ctot2 = $this->getTime($row['ctot_2'] ?? null);

            $booking->flights()->createMany([
                [
                    'order_by' => 1,
                    'dep' => $airport1,
                    'arr' => $airport2,
                    'ctot' => $ctot1,
                ],
                [
                    'order_by' => 2,
                    'dep' => $airport2,
                    'arr' => $airport3,
                    'ctot' => $ctot2,
                ],
            ]);
        } else {
            $flight = collect([
                'dep'          => $this->getAirport($row['origin']),
                'arr'          => $this->getAirport($row['destination']),
                'notes'        => $row['notes'] ?? null,
                'ctot'         => $this->getTime($row['ctot'] ?? null),
                'eta'          => $this->getTime($row['eta'] ?? null),
                'oceanicTrack' => $row['track'] ?? null,
                'oceanicFL'    => $row['fl'] ?? null,
                'route'        => $row['route'] ?? null,
            ]);
            $booking->flights()->create($flight->toArray());
        }
    }

    public function batchSize(): int
    {
        return 250;
    }

    public function chunkSize(): int
    {
        return 250;
    }

    public function rules(): array
    {
        if ($this->event->event_type_id == EventType::MULTIFLIGHTS->value) {
            return [
                'airport_1' => 'exists:airports,icao',
                'airport_2' => 'exists:airports,icao',
                'airport_3' => 'exists:airports,icao',
            ];
        }
        return [
            'origin'        => 'exists:airports,icao',
            'destination'   => 'exists:airports,icao',
            'airline'       => 'sometimes|nullable|string|max:3',
            'track'         => 'sometimes|nullable',
            'oceanicFL'     => 'sometimes|nullable|integer:3',
            'aircraft_type' => 'sometimes|nullable|max:4',
        ];
    }

    private function getAirport($icao): int
    {
        return Airport::whereIcao($icao)->first()->id;
    }

    private function getTime($time)
    {
        if (!empty($time)) {
            $time = Date::excelToDateTimeObject($time);
            $time->setDate(
                $this->event->startEvent->year,
                $this->event->startEvent->month,
                $this->event->startEvent->day,
            );
            return $time;
        }
        return null;
    }

    /**
     * Get airline ID from ICAO code, with validation and caching
     */
    private function getAirlineId(?string $icao): ?int
    {
        if (empty($icao)) {
            return null;
        }

        $icao = strtoupper(trim($icao));

        // Check if airline exists in cache
        foreach ($this->airlinesCache as $id => $displayName) {
            if ($id === '') {
                continue;
            } // Skip "No airline" option

            // Extract ICAO from display name (format: "ICAO | Name")
            $cachedIcao = explode(' | ', $displayName)[0] ?? '';
            if ($cachedIcao === $icao) {
                return (int) $id;
            }
        }

        // If not found in cache, try database lookup
        $airline = Airline::where('icao', $icao)->first();
        if ($airline) {
            return $airline->id;
        }

        // Airline not found - add to invalid list for warning
        if (!in_array($icao, $this->invalidAirlines)) {
            $this->invalidAirlines[] = $icao;
        }

        return null; // Set to null (no airline)
    }

    /**
     * Validate airline ICAO code without creating booking
     */
    public function validateAirline(?string $icao): void
    {
        if (empty($icao)) {
            return;
        }

        $icao = strtoupper(trim($icao));

        // Check if airline exists in cache
        foreach ($this->airlinesCache as $id => $displayName) {
            if ($id === '') {
                continue;
            } // Skip "No airline" option

            // Extract ICAO from display name (format: "ICAO | Name")
            $cachedIcao = explode(' | ', $displayName)[0] ?? '';
            if ($cachedIcao === $icao) {
                return; // Valid airline found
            }
        }

        // If not found in cache, try database lookup
        $airline = Airline::where('icao', $icao)->first();
        if ($airline) {
            return; // Valid airline found
        }

        // Airline not found - add to invalid list for warning
        if (!in_array($icao, $this->invalidAirlines)) {
            $this->invalidAirlines[] = $icao;
        }
    }

    /**
     * Get list of invalid airlines found during import
     */
    public function getInvalidAirlines(): array
    {
        return $this->invalidAirlines;
    }

    /**
     * Check if there are any invalid airlines
     */
    public function hasInvalidAirlines(): bool
    {
        return !empty($this->invalidAirlines);
    }
}
