<?php

namespace App\Services;

use App\Models\Airport;
use App\Models\Airline;
use App\Models\Bay;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CachedDataService
{
    /**
     * Get airports formatted for select dropdowns
     * Cached for 1 day (24 hours)
     */
    public function getAirportsForSelect(): Collection
    {
        try {
            return Cache::remember('airports_for_select', 86400, function () {
                return Airport::all(['id', 'icao', 'iata', 'name'])
                    ->keyBy('id')
                    ->map(function ($airport) {
                        /** @var Airport $airport */
                        return "$airport->icao | $airport->name | $airport->iata";
                    });
            });
        } catch (\Exception $e) {
            // Fallback to database if cache fails
            return Airport::all(['id', 'icao', 'iata', 'name'])
                ->keyBy('id')
                ->map(function ($airport) {
                    /** @var Airport $airport */
                    return "$airport->icao | $airport->name | $airport->iata";
                });
        }
    }

    /**
     * Get airlines formatted for select dropdowns
     * Cached for 1 hour (3600 seconds)
     */
    public function getAirlinesForSelect(): Collection
    {
        try {
            return Cache::remember('airlines_for_select', 3600, function () {
                $airlines = collect(['' => __('No airline')]);
                foreach (Airline::all(['id', 'icao', 'name']) as $airline) {
                    $airlines->put((string) $airline->id, "$airline->icao | $airline->name");
                }
                return $airlines;
            });
        } catch (\Exception $e) {
            // Fallback to database if cache fails
            $airlines = collect(['' => __('No airline')]);
            foreach (Airline::all(['id', 'icao', 'name']) as $airline) {
                $airlines->put((string) $airline->id, "$airline->icao | $airline->name");
            }
            return $airlines;
        }
    }

    /**
     * Get bays for an airport formatted for select dropdowns
     * Cached for 6 hours (21600 seconds) per airport
     *
     * @param int $airportId
     * @return Collection
     */
    public function getBaysForSelect(int $airportId): Collection
    {
        try {
            return Cache::remember("bays_for_select_airport_{$airportId}", 21600, function () use ($airportId) {
                $sortedBays = Bay::getSortedBaysForAirport($airportId);

                $bays = collect(['' => '-- No Bay --']);
                foreach ($sortedBays as $bay) {
                    $bays->put((string) $bay->id, $bay->name);
                }

                return $bays;
            });
        } catch (\Exception $e) {
            // Fallback to database if cache fails
            $sortedBays = Bay::getSortedBaysForAirport($airportId);

            $bays = collect(['' => '-- No Bay --']);
            foreach ($sortedBays as $bay) {
                $bays->put((string) $bay->id, $bay->name);
            }

            return $bays;
        }
    }

    /**
     * Get sorted bays for an airport (without select formatting)
     * Cached for 6 hours (21600 seconds) per airport
     *
     * @param int $airportId
     * @return Collection
     */
    public function getSortedBays(int $airportId): Collection
    {
        try {
            return Cache::remember("sorted_bays_airport_{$airportId}", 21600, function () use ($airportId) {
                return Bay::getSortedBaysForAirport($airportId);
            });
        } catch (\Exception $e) {
            // Fallback to database if cache fails
            return Bay::getSortedBaysForAirport($airportId);
        }
    }

    /**
     * Clear bays cache for a specific airport
     *
     * @param int $airportId
     */
    public function clearBaysCache(int $airportId): void
    {
        Cache::forget("bays_for_select_airport_{$airportId}");
        Cache::forget("sorted_bays_airport_{$airportId}");
    }

    /**
     * Clear bays cache for all airports
     */
    public function clearAllBaysCache(): void
    {
        // Get all airports and clear their bay caches
        $airportIds = Airport::pluck('id');
        foreach ($airportIds as $airportId) {
            $this->clearBaysCache($airportId);
        }
    }

    /**
     * Clear airports cache
     */
    public function clearAirportsCache(): void
    {
        Cache::forget('airports_for_select');
    }

    /**
     * Clear airlines cache
     */
    public function clearAirlinesCache(): void
    {
        Cache::forget('airlines_for_select');
    }

    /**
     * Clear all caches
     */
    public function clearAllCaches(): void
    {
        $this->clearAirportsCache();
        $this->clearAirlinesCache();
        $this->clearAllBaysCache();
    }
}
