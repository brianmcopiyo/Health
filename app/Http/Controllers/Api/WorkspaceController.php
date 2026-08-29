<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BedAssignment;
use App\Models\Encounter;
use App\Models\EncounterClinician;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Prescription;
use App\Models\Referral;
use App\Models\ServiceOrder;
use App\Models\StaffAssignment;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return [
            'role' => $user->role?->slug,
            'workspace' => $user->role?->workspace,
            'my_encounters' => Encounter::query()
                ->with(['patient:id,mrn,first_name,last_name,status', 'department:id,name', 'facility:id,name,code'])
                ->whereIn('status', ['waiting', 'in_progress'])
                ->where(function ($query) use ($user) {
                    $query->where('clinician_id', $user->id)
                        ->orWhereIn('id', EncounterClinician::query()->select('encounter_id')->where('user_id', $user->id));
                })
                ->latest()
                ->limit(20)
                ->get(),
            'ward_patients' => $this->wardPatients($user->id),
            'lab_orders' => $user->hasPermission('read', 'Laboratory')
                ? ServiceOrder::query()->with(['patient:id,mrn,first_name,last_name,status', 'encounter:id,type,status'])->where('module_key', 'laboratory')->whereIn('status', ['requested', 'collected', 'processing'])->latest()->limit(30)->get()
                : [],
            'imaging_orders' => $user->hasPermission('read', 'Imaging')
                ? ServiceOrder::query()->with(['patient:id,mrn,first_name,last_name,status', 'encounter:id,type,status'])->where('module_key', 'imaging')->whereIn('status', ['requested', 'scheduled', 'processing'])->latest()->limit(30)->get()
                : [],
            'prescriptions' => $user->hasPermission('read', 'Pharmacy')
                ? Prescription::query()->with(['patient:id,mrn,first_name,last_name,status', 'items.medication'])->whereIn('status', ['pending', 'verified'])->latest()->limit(30)->get()
                : [],
            'referrals' => $user->hasPermission('read', 'Referral')
                ? Referral::query()->with(['patient:id,mrn,first_name,last_name,status', 'fromHospital:id,name,code', 'toHospital:id,name,code'])->where('status', 'pending')->latest()->limit(20)->get()
                : [],
            'invoices' => $user->hasPermission('read', 'Invoice')
                ? Invoice::query()->with('patient:id,mrn,first_name,last_name,status')->whereIn('status', ['draft', 'issued'])->latest()->limit(20)->get()
                : [],
            'assignments' => StaffAssignment::query()->with(['department:id,name', 'facility:id,name,code'])->where('user_id', $user->id)->where('status', 'active')->get(),
        ];
    }

    private function wardPatients(string $userId)
    {
        $wardIds = StaffAssignment::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNotNull('facility_id')
            ->pluck('facility_id');

        $query = BedAssignment::query()
            ->with(['patient:id,mrn,first_name,last_name,status', 'facility:id,name,code,status,parent_id', 'encounter:id,type,status'])
            ->where('status', 'active');

        if ($wardIds->isEmpty()) {
            return $query->where(function ($inner) use ($userId) {
                $inner->where('nurse_id', $userId)
                    ->orWhereIn('encounter_id', EncounterClinician::query()->select('encounter_id')->where('user_id', $userId));
            })->limit(100)->get();
        }

        $bedIds = Facility::query()
            ->where(function ($inner) use ($wardIds) {
                $inner->whereIn('parent_id', $wardIds)->orWhereIn('id', $wardIds);
            })
            ->pluck('id');

        return $query->whereIn('facility_id', $bedIds)->limit(100)->get();
    }
}
