<?php

namespace App\Services;

use App\Models\Airport;
use App\Models\Airline;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CachedDataService
{
    /**
     * Get airports formatted for select dropdowns
     * Cached for 1 hour (3600 seconds)
     */
    public function getAirportsForSelect(): Collection
    {
        try {
            return Cache::remember('airports_for_select', 3600, function () {
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
                    $airlines->put($airline->id, "$airline->icao | $airline->name");
                }
                return $airlines;
            });
        } catch (\Exception $e) {
            // Fallback to database if cache fails
            $airlines = collect(['' => __('No airline')]);
            foreach (Airline::all(['id', 'icao', 'name']) as $airline) {
                $airlines->put($airline->id, "$airline->icao | $airline->name");
            }
            return $airlines;
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
    }
}
