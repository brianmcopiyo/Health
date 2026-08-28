<?php

namespace App\Support;

use App\Models\AmbulanceTrip;
use App\Models\BedAssignment;
use App\Models\ClinicalNote;
use App\Models\Diagnosis;
use App\Models\Dispensing;
use App\Models\Encounter;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Referral;
use App\Models\ServiceOrder;
use App\Models\Vital;

class PatientTimeline
{
    public static function for(Patient $patient): array
    {
        $events = collect();

        $events->push(self::event(
            $patient->created_at,
            'registration',
            'Registered',
            $patient->mrn.' added to the hospital register',
            null,
            null
        ));

        Encounter::query()->with(['clinician:id,name', 'department:id,name'])->where('patient_id', $patient->id)->latest()->limit(80)->get()->each(function (Encounter $encounter) use ($events) {
            $events->push(self::event(
                $encounter->created_at,
                'encounter',
                strtoupper($encounter->type).' encounter opened',
                $encounter->chief_complaint,
                $encounter->id,
                $encounter->clinician?->name
            ));
            if ($encounter->admitted_at) {
                $events->push(self::event($encounter->admitted_at, 'admission', 'Admitted', $encounter->department?->name, $encounter->id, $encounter->clinician?->name));
            }
            if ($encounter->discharged_at) {
                $events->push(self::event($encounter->discharged_at, 'discharge', 'Discharged', null, $encounter->id, $encounter->clinician?->name));
            }
        });

        Vital::query()->with('recordedBy:id,name')->where('patient_id', $patient->id)->latest('recorded_at')->limit(80)->get()->each(function (Vital $vital) use ($events) {
            $events->push(self::event($vital->recorded_at ?: $vital->created_at, 'vitals', 'Vitals recorded', $vital->summary(), $vital->encounter_id, $vital->recordedBy?->name));
        });

        ClinicalNote::query()->with('author:id,name')->where('patient_id', $patient->id)->latest('recorded_at')->limit(80)->get()->each(function (ClinicalNote $note) use ($events) {
            $events->push(self::event($note->recorded_at ?: $note->created_at, 'note', ucfirst($note->type).' note', mb_strimwidth($note->body, 0, 140, '…'), $note->encounter_id, $note->author?->name));
        });

        Diagnosis::query()->with('recordedBy:id,name')->where('patient_id', $patient->id)->latest('recorded_at')->limit(80)->get()->each(function (Diagnosis $diagnosis) use ($events) {
            $events->push(self::event($diagnosis->recorded_at ?: $diagnosis->created_at, 'diagnosis', $diagnosis->kind === 'primary' ? 'Primary diagnosis' : 'Diagnosis', $diagnosis->name, $diagnosis->encounter_id, $diagnosis->recordedBy?->name));
        });

        ServiceOrder::query()->with('orderedBy:id,name')->where('patient_id', $patient->id)->latest()->limit(80)->get()->each(function (ServiceOrder $order) use ($events) {
            $events->push(self::event($order->requested_at ?: $order->created_at, 'order', ucfirst($order->order_type ?: $order->module_key).' ordered', $order->item_name, $order->encounter_id, $order->orderedBy?->name));
            if ($order->status === 'completed') {
                $events->push(self::event($order->completed_at ?: $order->updated_at, 'result', $order->item_name.' result', $order->result, $order->encounter_id, null));
            }
        });

        Prescription::query()->with('prescribedBy:id,name')->where('patient_id', $patient->id)->latest()->limit(80)->get()->each(function (Prescription $rx) use ($events) {
            $events->push(self::event($rx->prescribed_at ?: $rx->created_at, 'prescription', 'Prescription written', $rx->notes, $rx->encounter_id, $rx->prescribedBy?->name));
        });

        Dispensing::query()->with(['medication:id,name,strength,form', 'dispensedBy:id,name'])->where('patient_id', $patient->id)->latest('dispensed_at')->limit(80)->get()->each(function (Dispensing $row) use ($events) {
            $events->push(self::event($row->dispensed_at ?: $row->created_at, 'dispense', 'Medication dispensed', $row->medication?->label().' × '.$row->quantity, $row->encounter_id, $row->dispensedBy?->name));
        });

        BedAssignment::query()->with('facility:id,name,code')->where('patient_id', $patient->id)->latest()->limit(40)->get()->each(function (BedAssignment $assignment) use ($events) {
            $events->push(self::event($assignment->assigned_at ?: $assignment->created_at, 'bed', 'Assigned to '.$assignment->facility?->name, $assignment->facility?->code, $assignment->encounter_id, null));
            if ($assignment->discharged_at) {
                $events->push(self::event($assignment->discharged_at, 'bed', 'Left '.$assignment->facility?->name, null, $assignment->encounter_id, null));
            }
        });

        Invoice::query()->where('patient_id', $patient->id)->latest()->limit(40)->get()->each(function (Invoice $invoice) use ($events) {
            $events->push(self::event($invoice->created_at, 'billing', 'Invoice '.$invoice->number, 'Total '.$invoice->total, $invoice->encounter_id, null));
        });

        Payment::query()->with(['invoice:id,encounter_id,patient_id', 'receivedBy:id,name'])->where('patient_id', $patient->id)->latest('received_at')->limit(40)->get()->each(function (Payment $payment) use ($events) {
            $events->push(self::event($payment->received_at ?: $payment->created_at, 'payment', 'Payment received', $payment->method.' · '.$payment->amount, $payment->invoice?->encounter_id, $payment->receivedBy?->name));
        });

        Referral::query()
            ->with(['fromHospital', 'toHospital', 'referringClinician'])
            ->where(function ($query) use ($patient) {
                $query->where('patient_id', $patient->id)
                    ->orWhere('receiving_patient_id', $patient->id);
            })
            ->limit(40)
            ->get()
            ->each(function (Referral $referral) use ($events, $patient) {
            $outgoing = $referral->patient_id === $patient->id;
            $events->push(self::event(
                $referral->created_at,
                'referral',
                $outgoing ? 'Referral sent' : 'Referral received',
                ($referral->fromHospital?->name ?? '').' → '.($referral->toHospital?->name ?? ''),
                $outgoing ? $referral->encounter_id : $referral->receiving_encounter_id,
                $referral->referringClinician?->name
            ));
        });

        AmbulanceTrip::query()->with('driver:id,name')->where('patient_id', $patient->id)->latest()->limit(20)->get()->each(function (AmbulanceTrip $trip) use ($events) {
            $events->push(self::event($trip->dispatched_at ?: $trip->created_at, 'ambulance', 'Ambulance '.$trip->status, trim($trip->origin.' → '.$trip->destination), $trip->encounter_id, $trip->driver?->name));
        });

        return $events
            ->filter(fn ($event) => $event['at'])
            ->sortByDesc('at')
            ->take(250)
            ->values()
            ->all();
    }

    private static function event($at, string $type, string $title, ?string $detail, $encounterId, ?string $actor): array
    {
        return [
            'at' => optional($at)?->toIso8601String(),
            'type' => $type,
            'title' => $title,
            'detail' => $detail,
            'encounter_id' => $encounterId,
            'actor' => $actor,
        ];
    }
}
