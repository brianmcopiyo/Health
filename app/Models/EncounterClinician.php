<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EncounterClinician extends BaseModel
{
    protected $fillable = ['encounter_id', 'user_id', 'care_role'];

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
