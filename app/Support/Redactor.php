<?php

namespace App\Support;

use App\Models\User;

class Redactor
{
    public static function patient(array $payload, User $user, bool $list = false): array
    {
        $profile = Access::profile($user);

        if ($list) {
            $keep = ['id', 'mrn', 'first_name', 'last_name', 'full_name', 'sex', 'status'];
            if (in_array($profile, ['full', 'financial', 'ambulance'], true)) {
                $keep[] = 'phone';
                $keep[] = 'date_of_birth';
            }

            return array_intersect_key($payload, array_flip($keep));
        }

        return match ($profile) {
            'financial' => self::only($payload, [
                'id', 'mrn', 'first_name', 'last_name', 'full_name', 'sex', 'date_of_birth', 'phone', 'status',
                'invoices', 'encounters',
            ]),
            'laboratory' => self::only($payload, [
                'id', 'mrn', 'first_name', 'last_name', 'full_name', 'sex', 'status', 'allergies', 'orders', 'encounters',
            ]),
            'imaging' => self::only($payload, [
                'id', 'mrn', 'first_name', 'last_name', 'full_name', 'sex', 'status', 'orders', 'encounters',
            ]),
            'pharmacy' => self::only($payload, [
                'id', 'mrn', 'first_name', 'last_name', 'full_name', 'sex', 'status', 'allergies', 'prescriptions', 'encounters',
            ]),
            'theatre' => self::only($payload, [
                'id', 'mrn', 'first_name', 'last_name', 'full_name', 'sex', 'status', 'allergies', 'orders', 'encounters',
            ]),
            'ambulance' => self::only($payload, [
                'id', 'mrn', 'first_name', 'last_name', 'full_name', 'sex', 'phone', 'status', 'encounters',
            ]),
            default => $payload,
        };
    }

    public static function encounter(array $payload, User $user): array
    {
        $profile = Access::profile($user);

        return match ($profile) {
            'financial' => self::only($payload, [
                'id', 'type', 'status', 'started_at', 'completed_at', 'patient', 'department', 'invoices',
            ]),
            'laboratory' => self::only($payload, [
                'id', 'type', 'status', 'chief_complaint', 'patient', 'orders', 'diagnoses',
            ]),
            'imaging' => self::only($payload, [
                'id', 'type', 'status', 'chief_complaint', 'patient', 'orders',
            ]),
            'pharmacy' => self::only($payload, [
                'id', 'type', 'status', 'patient', 'prescriptions', 'diagnoses',
            ]),
            'ambulance' => self::only($payload, [
                'id', 'type', 'status', 'chief_complaint', 'patient', 'referral',
            ]),
            'theatre' => self::only($payload, [
                'id', 'type', 'status', 'chief_complaint', 'patient', 'orders', 'diagnoses', 'care_plans',
            ]),
            default => $payload,
        };
    }

    private static function only(array $payload, array $keys): array
    {
        return array_intersect_key($payload, array_flip($keys));
    }
}
