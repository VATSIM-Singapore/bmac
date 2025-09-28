<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $event_id
 * @property string $callsign
 * @property string $acType
 * @property int|null $dep
 * @property int|null $arr
 * @property int|null $bay_id
 * @property \Illuminate\Support\Carbon|null $bay_assigned_from
 * @property \Illuminate\Support\Carbon|null $bay_assigned_to
 * @property \Illuminate\Support\Carbon|null $std
 * @property \Illuminate\Support\Carbon|null $sta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\Airport|null $airportDep
 * @property-read \App\Models\Airport|null $airportArr
 * @property-read \App\Models\Bay|null $bay
 * @property-read \App\Models\Event $event
 * @property-read string $formatted_callsign
 * @property-read string $formatted_actype
 * @property-read string $formatted_std
 * @property-read string $formatted_sta
 * @property-write mixed $callsign
 * @property-write mixed $actype
 */
class AdhocFlight extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'bay_assigned_from' => 'datetime',
        'bay_assigned_to' => 'datetime',
        'std' => 'datetime',
        'sta' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty();
    }

    public function getFormattedCallsignAttribute(): string
    {
        return $this->callsign ?: '-';
    }

    public function getFormattedActypeAttribute(): string
    {
        return $this->acType ?: '-';
    }

    public function getFormattedStdAttribute(): string
    {
        if (!empty($this->std)) {
            return $this->std->format('Hi') . 'z';
        }
        return '-';
    }

    public function getFormattedStaAttribute(): string
    {
        if (!empty($this->sta)) {
            return $this->sta->format('Hi') . 'z';
        }
        return '-';
    }

    public function setCallsignAttribute($value): void
    {
        $this->attributes['callsign'] = !empty($value) ? strtoupper($value) : null;
    }

    public function setActypeAttribute($value): void
    {
        $this->attributes['acType'] = !empty($value) ? strtoupper($value) : null;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function airportDep(): HasOne
    {
        return $this->hasOne(Airport::class, 'id', 'dep')->withDefault();
    }

    public function airportArr(): HasOne
    {
        return $this->hasOne(Airport::class, 'id', 'arr')->withDefault();
    }

    public function bay(): BelongsTo
    {
        return $this->belongsTo(Bay::class);
    }
}
