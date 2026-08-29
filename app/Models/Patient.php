<?php

namespace App\Models;

use App\Casts\Encrypted;
use App\Models\Concerns\BelongsToHospital;
use App\Support\FieldCrypt;
use App\Support\PatientTimeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends BaseModel
{
    use BelongsToHospital;

    public const STATUSES = ['active', 'admitted', 'discharged', 'deceased', 'transferred'];

    protected $appends = ['full_name'];

    protected $fillable = [
        'hospital_id',
        'source_patient_id',
        'mrn',
        'first_name',
        'last_name',
        'sex',
        'date_of_birth',
        'phone',
        'email',
        'national_id',
        'blood_group',
        'marital_status',
        'occupation',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'next_of_kin_name',
        'next_of_kin_phone',
        'next_of_kin_relation',
        'status',
        'notes',
    ];

    protected $hidden = [
        'phone_index',
        'phone_tail_index',
        'email_index',
        'national_id_index',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'phone' => Encrypted::class.':phone_index=phone,phone_tail_index=phone_tail',
            'email' => Encrypted::class.':email_index=email',
            'national_id' => Encrypted::class.':national_id_index=national_id',
            'address' => Encrypted::class,
            'emergency_contact_name' => Encrypted::class,
            'emergency_contact_phone' => Encrypted::class,
            'next_of_kin_name' => Encrypted::class,
            'next_of_kin_phone' => Encrypted::class,
            'notes' => Encrypted::class,
            'retention_until' => 'datetime',
            'archived_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $patient) {
            $patient->retention_until ??= now()->addYears((int) config('hms.retention_years', 7));
        });
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function sourcePatient(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_patient_id');
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function bedAssignments(): HasMany
    {
        return $this->hasMany(BedAssignment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class);
    }

    public function currentAllergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class)->where('is_current', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $prefix = addcslashes($term, '%_').'%';
        $phone = FieldCrypt::normalizePhone($term);
        $email = FieldCrypt::normalizeEmail($term);
        $nid = FieldCrypt::normalizeNationalId($term);

        return $query->where(function (Builder $builder) use ($term, $prefix, $phone, $email, $nid) {
            $builder->where('mrn', 'like', $prefix)
                ->orWhere('first_name', 'like', $prefix)
                ->orWhere('last_name', 'like', $prefix);

            if ($phone) {
                $builder->orWhere('phone_index', FieldCrypt::blindIndex($phone));
                if (strlen($phone) === 4) {
                    $builder->orWhere('phone_tail_index', FieldCrypt::blindIndex($phone));
                }
            }

            if ($email && str_contains($email, '@')) {
                $builder->orWhere('email_index', FieldCrypt::blindIndex($email));
            }

            if ($nid) {
                $builder->orWhere('national_id_index', FieldCrypt::blindIndex($nid));
            }
        });
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(PatientCondition::class);
    }

    public function vitals(): HasMany
    {
        return $this->hasMany(Vital::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function ambulanceTrips(): HasMany
    {
        return $this->hasMany(AmbulanceTrip::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClinicalDocument::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function fullName(): string
    {
        return $this->full_name;
    }

    public function timeline(): array
    {
        return PatientTimeline::for($this);
    }

    public function activeBed(): ?BedAssignment
    {
        return $this->bedAssignments()->where('status', 'active')->latest('assigned_at')->first();
    }
}
