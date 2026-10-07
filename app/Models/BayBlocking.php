<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Services\CachedDataService;

class BayBlocking extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'event_id',
        'bay_id',
    ];

    protected static function booted(): void
    {
        // Clear cache when a bay blocking is created, updated, or deleted
        static::saved(function (BayBlocking $bayBlocking) {
            $cachedDataService = new CachedDataService();
            $cachedDataService->clearBaysCache($bayBlocking->bay_id, $bayBlocking->event_id);
        });

        static::deleted(function (BayBlocking $bayBlocking) {
            $cachedDataService = new CachedDataService();
            $cachedDataService->clearBaysCache($bayBlocking->bay_id, $bayBlocking->event_id);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function bay(): BelongsTo
    {
        return $this->belongsTo(Bay::class);
    }
}
