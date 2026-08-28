<?php

namespace App\Support;

use App\Models\Encounter;

class ClinicalPayload
{
    public static function encounter(Encounter $encounter): array
    {
        $encounter->loadMissing([
            'patient.currentAllergies',
            'patient.conditions' => fn ($conditions) => $conditions->where('status', 'active'),
            'department',
            'clinician',
            'facility',
            'careTeam.user',
            'vitals.recordedBy',
            'clinicalNotes.author',
            'diagnoses.recordedBy',
            'carePlans.createdBy',
            'orders.orderedBy',
            'orders.service',
            'prescriptions.items.medication',
            'prescriptions.prescribedBy',
            'invoices.items',
            'bedAssignments.facility',
            'referral.fromHospital',
            'referral.toHospital',
        ]);

        if ($encounter->patient && $encounter->patient->relationLoaded('currentAllergies')) {
            $encounter->patient->setRelation('allergies', $encounter->patient->currentAllergies);
        }

        return [
            'id' => $encounter->id,
            'type' => $encounter->type,
            'status' => $encounter->status,
            'chief_complaint' => $encounter->chief_complaint,
            'notes' => $encounter->notes,
            'started_at' => $encounter->started_at,
            'completed_at' => $encounter->completed_at,
            'admitted_at' => $encounter->admitted_at,
            'discharged_at' => $encounter->discharged_at,
            'patient' => $encounter->patient,
            'department' => $encounter->department,
            'clinician' => $encounter->clinician,
            'facility' => $encounter->facility,
            'care_team' => $encounter->careTeam,
            'vitals' => $encounter->vitals,
            'clinical_notes' => $encounter->clinicalNotes,
            'diagnoses' => $encounter->diagnoses,
            'care_plans' => $encounter->carePlans,
            'orders' => $encounter->orders,
            'prescriptions' => $encounter->prescriptions,
            'invoices' => $encounter->invoices,
            'bed_assignments' => $encounter->bedAssignments,
            'referral' => $encounter->referral,
        ];
    }
}
