<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BedAssignment;
use App\Models\Encounter;
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
                ->with(['patient', 'department', 'facility'])
                ->whereIn('status', ['waiting', 'in_progress'])
                ->where(function ($query) use ($user) {
                    $query->where('clinician_id', $user->id)
                        ->orWhereHas('careTeam', fn ($team) => $team->where('user_id', $user->id));
                })
                ->latest()
                ->limit(20)
                ->get(),
            'ward_patients' => $this->wardPatients($user->id),
            'lab_orders' => $user->hasPermission('read', 'Laboratory')
                ? ServiceOrder::query()->with(['patient', 'encounter'])->where('module_key', 'laboratory')->whereIn('status', ['requested', 'collected', 'processing'])->latest()->limit(30)->get()
                : [],
            'imaging_orders' => $user->hasPermission('read', 'Imaging')
                ? ServiceOrder::query()->with(['patient', 'encounter'])->where('module_key', 'imaging')->whereIn('status', ['requested', 'scheduled', 'processing'])->latest()->limit(30)->get()
                : [],
            'prescriptions' => $user->hasPermission('read', 'Pharmacy')
                ? Prescription::query()->with(['patient', 'items.medication'])->whereIn('status', ['pending', 'verified'])->latest()->limit(30)->get()
                : [],
            'referrals' => $user->hasPermission('read', 'Referral')
                ? Referral::query()->with(['patient', 'fromHospital', 'toHospital'])->where('status', 'pending')->latest()->limit(20)->get()
                : [],
            'invoices' => $user->hasPermission('read', 'Invoice')
                ? Invoice::query()->with('patient')->whereIn('status', ['draft', 'issued'])->latest()->limit(20)->get()
                : [],
            'assignments' => StaffAssignment::query()->with(['department', 'facility'])->where('user_id', $user->id)->where('status', 'active')->get(),
        ];
    }

    private function wardPatients(int $userId)
    {
        $wardIds = StaffAssignment::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereNotNull('facility_id')
            ->pluck('facility_id');

        if ($wardIds->isEmpty()) {
            return BedAssignment::query()
                ->with(['patient', 'facility', 'encounter'])
                ->where('status', 'active')
                ->where(function ($query) use ($userId) {
                    $query->where('nurse_id', $userId)->orWhereHas('encounter.careTeam', fn ($team) => $team->where('user_id', $userId));
                })
                ->get();
        }

        $bedIds = Facility::query()->whereIn('parent_id', $wardIds)->orWhereIn('id', $wardIds)->pluck('id');

        return BedAssignment::query()
            ->with(['patient', 'facility', 'encounter'])
            ->where('status', 'active')
            ->whereIn('facility_id', $bedIds)
            ->get();
    }
}
