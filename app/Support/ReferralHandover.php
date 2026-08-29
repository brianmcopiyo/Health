<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReferralHandover
{
    public static function accept(Referral $referral, User $reviewer, Facility $facility): Patient
    {
        return DB::transaction(function () use ($referral, $reviewer, $facility) {
            $origin = Patient::withoutGlobalScope('hospital')->find($referral->patient_id);
            $destination = $origin
                ? self::mirrorPatient($origin, $referral->to_hospital_id)
                : self::createNamedPatient($referral, $referral->to_hospital_id);

            $department = Department::withoutGlobalScope('hospital')
                ->where('hospital_id', $referral->to_hospital_id)
                ->where('slug', 'emergency')
                ->first(['id', 'hospital_id', 'slug']);

            $encounter = Encounter::withoutGlobalScope('hospital')->create([
                'hospital_id' => $referral->to_hospital_id,
                'patient_id' => $destination->id,
                'department_id' => $department?->id,
                'facility_id' => $facility->id,
                'referral_id' => $referral->id,
                'parent_encounter_id' => $referral->encounter_id,
                'type' => 'referral',
                'status' => 'waiting',
                'chief_complaint' => $referral->reason,
            ]);

            $referral->forceFill([
                'receiving_patient_id' => $destination->id,
                'receiving_encounter_id' => $encounter->id,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            return $destination;
        });
    }

    private static function mirrorPatient(Patient $origin, string $hospitalId): Patient
    {
        $existing = Patient::withoutGlobalScope('hospital')
            ->where('hospital_id', $hospitalId)
            ->where(function ($query) use ($origin) {
                $query->where('source_patient_id', $origin->id)
                    ->orWhere(function ($inner) use ($origin) {
                        $inner->where('first_name', $origin->first_name)
                            ->where('last_name', $origin->last_name)
                            ->where('phone', $origin->phone)
                            ->whereNotNull('phone');
                    });
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        $hospital = Hospital::query()->find($hospitalId);

        return Patient::withoutGlobalScope('hospital')->create([
            'hospital_id' => $hospitalId,
            'source_patient_id' => $origin->id,
            'mrn' => HospitalSequence::nextMrn($hospital),
            'first_name' => $origin->first_name,
            'last_name' => $origin->last_name,
            'sex' => $origin->sex,
            'date_of_birth' => $origin->date_of_birth,
            'phone' => $origin->phone,
            'address' => $origin->address,
            'blood_group' => $origin->blood_group,
            'next_of_kin_name' => $origin->next_of_kin_name,
            'next_of_kin_phone' => $origin->next_of_kin_phone,
            'status' => 'active',
        ]);
    }

    private static function createNamedPatient(Referral $referral, string $hospitalId): Patient
    {
        $parts = preg_split('/\s+/', trim($referral->patient_name)) ?: ['Unknown'];
        $hospital = Hospital::query()->find($hospitalId);

        return Patient::withoutGlobalScope('hospital')->create([
            'hospital_id' => $hospitalId,
            'mrn' => HospitalSequence::nextMrn($hospital),
            'first_name' => $parts[0],
            'last_name' => $parts[1] ?? $parts[0],
            'phone' => $referral->patient_reference,
            'status' => 'active',
        ]);
    }
}
