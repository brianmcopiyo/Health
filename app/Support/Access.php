<?php

namespace App\Support;

use App\Models\BedAssignment;
use App\Models\Encounter;
use App\Models\EncounterClinician;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Referral;
use App\Models\ServiceOrder;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class Access
{
    public static function canViewPatient(User $user, Patient $patient): bool
    {
        if (! $user->hasPermission('read', 'Patient')) {
            return false;
        }

        if ($user->isPlatformAdmin()) {
            return false;
        }

        if ((string) $patient->hospital_id !== (string) $user->hospital_id) {
            return false;
        }

        if (self::hasBroadPatientAccess($user)) {
            return true;
        }

        return self::isAssignedToPatient($user, $patient);
    }

    public static function canUpdatePatient(User $user, Patient $patient): bool
    {
        return self::canViewPatient($user, $patient) && $user->hasPermission('update', 'Patient');
    }

    public static function canViewEncounter(User $user, Encounter $encounter): bool
    {
        if ($user->isPlatformAdmin()) {
            return false;
        }

        if ((string) $encounter->hospital_id !== (string) $user->hospital_id) {
            return false;
        }

        $subject = self::subjectForEncounterType($encounter->type);
        if ($user->hasPermission('read', $subject) || $user->hasPermission('update', $subject) || $user->hasPermission('create', $subject)) {
            return true;
        }

        if ((string) $encounter->clinician_id === (string) $user->id) {
            return true;
        }

        if (EncounterClinician::query()->where('encounter_id', $encounter->id)->where('user_id', $user->id)->exists()) {
            return true;
        }

        foreach (self::orderModulesFor($user) as $module) {
            if (ServiceOrder::query()->where('encounter_id', $encounter->id)->where('module_key', $module)->exists()) {
                return true;
            }
        }

        if ($user->hasPermission('read', 'Pharmacy') && Prescription::query()->where('encounter_id', $encounter->id)->exists()) {
            return true;
        }

        if ($user->hasPermission('read', 'Invoice') && $encounter->invoices()->exists()) {
            return true;
        }

        if ($user->hasPermission('read', 'Ambulance') && $encounter->ambulance_trip_id) {
            return true;
        }

        if ($user->hasPermission('read', 'Invoice') && self::hasBroadPatientAccess($user)) {
            return true;
        }

        return false;
    }

    public static function canUpdateEncounter(User $user, Encounter $encounter): bool
    {
        if ($user->isPlatformAdmin()) {
            return false;
        }

        if ((string) $encounter->hospital_id !== (string) $user->hospital_id) {
            return false;
        }

        $subject = self::subjectForEncounterType($encounter->type);
        if ($user->hasPermission('update', $subject) || $user->hasPermission('create', $subject)) {
            return true;
        }

        if ($user->hasPermission('update', 'Opd') && in_array($encounter->type, ['opd', 'follow_up', 'emergency', 'admission'], true)) {
            return true;
        }

        if ($user->hasPermission('update', 'Emergency') && $encounter->type === 'admission') {
            return true;
        }

        return false;
    }

    public static function canExportPatient(User $user, Patient $patient): bool
    {
        return self::canViewPatient($user, $patient)
            && ($user->hasPermission('manage', 'Patient') || $user->hasPermission('manage', 'all'));
    }

    public static function patientQuery(User $user, Builder $query, bool $compact = false): Builder
    {
        if ($user->isPlatformAdmin()) {
            return $query->whereRaw('0 = 1');
        }

        if ($compact && ! self::limitsDirectory($user)) {
            return $query;
        }

        if (! self::limitsDirectory($user) && self::hasBroadPatientAccess($user)) {
            return $query;
        }

        return self::scopeAssignedPatients($user, $query);
    }

    public static function encounterQuery(User $user, Builder $query, ?string $type = null): Builder
    {
        if ($user->isPlatformAdmin()) {
            return $query->whereRaw('0 = 1');
        }

        if ($type) {
            return $query;
        }

        $types = self::visibleEncounterTypes($user);
        $modules = self::orderModulesFor($user);

        return $query->where(function (Builder $builder) use ($user, $types, $modules) {
            if ($types) {
                $builder->whereIn('type', $types);
            }

            $builder->orWhere('clinician_id', $user->id)
                ->orWhereIn('id', EncounterClinician::query()->select('encounter_id')->where('user_id', $user->id));

            if ($modules) {
                $builder->orWhereIn('id', ServiceOrder::query()->select('encounter_id')->whereIn('module_key', $modules)->whereNotNull('encounter_id'));
            }

            if ($user->hasPermission('read', 'Pharmacy')) {
                $builder->orWhereIn('id', Prescription::query()->select('encounter_id'));
            }
        });
    }

    public static function profile(User $user): string
    {
        $slug = $user->role?->slug;

        return match ($slug) {
            'billing-clerk' => 'financial',
            'lab-staff' => 'laboratory',
            'imaging-staff' => 'imaging',
            'pharmacy-staff' => 'pharmacy',
            'ambulance-team' => 'ambulance',
            'theatre-staff' => 'theatre',
            default => 'full',
        };
    }

    public static function hasBroadPatientAccess(User $user): bool
    {
        return in_array($user->role?->slug, [
            'administrator',
            'doctor',
            'reception',
            'emergency-staff',
            'billing-clerk',
            'nurse',
            'ambulance-team',
        ], true);
    }

    public static function limitsDirectory(User $user): bool
    {
        return in_array($user->role?->slug, [
            'lab-staff',
            'imaging-staff',
            'pharmacy-staff',
            'theatre-staff',
        ], true);
    }

    public static function subjectForEncounterType(string $type): string
    {
        return match ($type) {
            'opd', 'follow_up' => 'Opd',
            'emergency' => 'Emergency',
            'admission' => 'Ward',
            'procedure' => 'Theatre',
            'referral' => 'Referral',
            default => 'Reception',
        };
    }

    public static function visibleEncounterTypes(User $user): array
    {
        $map = [
            'Opd' => ['opd', 'follow_up'],
            'Emergency' => ['emergency'],
            'Ward' => ['admission'],
            'Theatre' => ['procedure'],
            'Referral' => ['referral'],
            'Reception' => ['reception'],
        ];

        $types = [];
        foreach ($map as $subject => $values) {
            if ($user->hasPermission('read', $subject) || $user->hasPermission('create', $subject) || $user->hasPermission('update', $subject)) {
                $types = array_merge($types, $values);
            }
        }

        return array_values(array_unique($types));
    }

    public static function orderModulesFor(User $user): array
    {
        $modules = [];
        if ($user->hasPermission('read', 'Laboratory') || $user->hasPermission('update', 'Laboratory')) {
            $modules[] = 'laboratory';
        }
        if ($user->hasPermission('read', 'Imaging') || $user->hasPermission('update', 'Imaging')) {
            $modules[] = 'imaging';
        }
        if ($user->hasPermission('read', 'Theatre') || $user->hasPermission('update', 'Theatre')) {
            $modules[] = 'theatre';
        }

        return $modules;
    }

    private static function isAssignedToPatient(User $user, Patient $patient): bool
    {
        if (Encounter::query()->where('patient_id', $patient->id)->where('clinician_id', $user->id)->exists()) {
            return true;
        }

        if (EncounterClinician::query()
            ->where('user_id', $user->id)
            ->whereIn('encounter_id', Encounter::query()->select('id')->where('patient_id', $patient->id))
            ->exists()) {
            return true;
        }

        if (BedAssignment::query()->where('patient_id', $patient->id)->where('nurse_id', $user->id)->where('status', 'active')->exists()) {
            return true;
        }

        $wardIds = StaffAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('facility_id')
            ->pluck('facility_id');

        if ($wardIds->isNotEmpty() && BedAssignment::query()->where('patient_id', $patient->id)->where('status', 'active')->whereIn('ward_id', $wardIds)->exists()) {
            return true;
        }

        foreach (self::orderModulesFor($user) as $module) {
            if (ServiceOrder::query()->where('patient_id', $patient->id)->where('module_key', $module)->exists()) {
                return true;
            }
        }

        if ($user->hasPermission('read', 'Pharmacy') && Prescription::query()->where('patient_id', $patient->id)->exists()) {
            return true;
        }

        if ($user->hasPermission('read', 'Ambulance') && (
            $patient->ambulanceTrips()->exists()
            || Referral::query()->where(function ($query) use ($patient) {
                $query->where('patient_id', $patient->id)
                    ->orWhere('receiving_patient_id', $patient->id);
            })->exists()
        )) {
            return true;
        }

        return false;
    }

    private static function scopeAssignedPatients(User $user, Builder $query): Builder
    {
        $ids = collect();

        $ids = $ids->merge(Encounter::query()->where('clinician_id', $user->id)->pluck('patient_id'));
        $ids = $ids->merge(
            Encounter::query()
                ->whereIn('id', EncounterClinician::query()->select('encounter_id')->where('user_id', $user->id))
                ->pluck('patient_id')
        );
        $ids = $ids->merge(BedAssignment::query()->where('nurse_id', $user->id)->where('status', 'active')->pluck('patient_id'));

        foreach (self::orderModulesFor($user) as $module) {
            $ids = $ids->merge(ServiceOrder::query()->where('module_key', $module)->pluck('patient_id'));
        }

        if ($user->hasPermission('read', 'Pharmacy')) {
            $ids = $ids->merge(Prescription::query()->pluck('patient_id'));
        }

        return $query->whereIn('id', $ids->unique()->filter()->all() ?: ['00000000-0000-0000-0000-000000000000']);
    }
}
