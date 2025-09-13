<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BayBlocking extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'event_id',
        'bay_id',
    ];

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
