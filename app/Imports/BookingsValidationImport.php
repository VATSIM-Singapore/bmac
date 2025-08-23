<?php

namespace App\Imports;

use App\Models\Event;
use App\Models\Airline;
use App\Services\CachedDataService;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\ToArray;
use Illuminate\Support\Collection;

class BookingsValidationImport implements ToArray, WithHeadingRow
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
     * Validate each row for invalid airlines
     */
    public function validateRow(array $row): void
    {
        // Validate airline ICAO code
        $this->validateAirline($row['airline'] ?? null);
    }

    /**
     * Validate airline ICAO code
     */
    private function validateAirline(?string $icao): void
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
     * Get list of invalid airlines found during validation
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

    /**
     * Convert Excel rows to array and validate
     */
    public function array(array $rows): void
    {
        foreach ($rows as $row) {
            $this->validateRow($row);
        }
    }

    /**
     * Validate the entire file
     */
    public function validateFile($file): void
    {
        \Maatwebsite\Excel\Facades\Excel::import($this, $file);
    }
}
