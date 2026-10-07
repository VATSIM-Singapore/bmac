<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Services\CachedDataService;

class Bay extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'airport_id',
        'name',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // Clear cache when a bay is created, updated, or deleted
        static::saved(function (Bay $bay) {
            $cachedDataService = new CachedDataService();
            $cachedDataService->clearBaysCache($bay->airport_id);
        });

        static::deleted(function (Bay $bay) {
            $cachedDataService = new CachedDataService();
            $cachedDataService->clearBaysCache($bay->airport_id);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty();
    }

    public function airport(): BelongsTo
    {
        return $this->belongsTo(Airport::class);
    }

    public function bayBlockings(): HasMany
    {
        return $this->hasMany(BayBlocking::class);
    }

    /**
     * Get bays for an airport sorted using custom logic
     *
     * @param int $airportId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getSortedBaysForAirport($airportId)
    {
        return static::where('airport_id', $airportId)
            ->get()
            ->sortBy(function ($bay) {
                $name = $bay->name;

                // Check if the name starts with a letter
                if (preg_match('/^[A-Z]/', $name)) {
                    // Letter-prefixed gates: extract letter, number, and suffix for proper sorting
                    if (preg_match('/^([A-Z]+)(\d+)([A-Z]*)$/', $name, $matches)) {
                        $letter = $matches[1];      // "A", "C", etc.
                        $number = (int)$matches[2]; // 1, 17, etc.
                        $suffix = $matches[3] ?? ''; // "L", "R", etc.

                        // Create sortable key: letter + padded number + suffix
                        // This ensures A1 < A2 < A10 < C1 < C17L < C17R
                        return $letter . str_pad($number, 5, '0', STR_PAD_LEFT) . $suffix;
                    }
                    // If it doesn't match the pattern, put it with letter gates but sort by name
                    return '0' . $name;
                } else {
                    // Numeric-only gates: pad with zeros and prefix with 'ZZ' to put them last
                    if (is_numeric($name)) {
                        return 'ZZ' . str_pad($name, 5, '0', STR_PAD_LEFT);
                    }
                    // Other formats go last
                    return 'ZZ' . $name;
                }
            })
            ->values(); // Reset array keys
    }
}
