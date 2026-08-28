<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ambulance;
use App\Models\AssistanceRequest;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Referral;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $facilityQuery = Facility::query();
        $referralQuery = Referral::query();
        $assistanceQuery = AssistanceRequest::query();
        $ambulanceQuery = Ambulance::query();

        $facilityStats = [
            'total' => (clone $facilityQuery)->count(),
            'available' => (clone $facilityQuery)->where('status', 'available')->count(),
            'occupied' => (clone $facilityQuery)->where('status', 'occupied')->count(),
            'maintenance' => (clone $facilityQuery)->where('status', 'maintenance')->count(),
            'reserved' => (clone $facilityQuery)->where('status', 'reserved')->count(),
            'unavailable' => (clone $facilityQuery)->where('status', 'unavailable')->count(),
            'capacity' => (clone $facilityQuery)->sum('capacity'),
            'utilization' => (clone $facilityQuery)->sum('current_utilization'),
        ];

        $facilitiesByType = Facility::query()
            ->selectRaw('facility_type_id, count(*) as total, sum(capacity) as capacity, sum(current_utilization) as utilization')
            ->with('type:id,name,slug,icon')
            ->groupBy('facility_type_id')
            ->get();

        return response()->json([
            'hospital' => $user->isPlatformAdmin()
                ? ['id' => null, 'name' => 'Network', 'code' => 'NET']
                : $user->hospital,
            'networkHospitals' => $user->isPlatformAdmin() ? Hospital::query()->where('is_active', true)->count() : null,
            'facilities' => $facilityStats,
            'facilitiesByType' => $facilitiesByType,
            'patients' => [
                'total' => Patient::query()->count(),
                'active' => Patient::query()->whereNotIn('status', ['discharged', 'deceased'])->count(),
                'admitted' => Patient::query()->where('status', 'admitted')->count(),
            ],
            'encounters' => [
                'waiting' => Encounter::query()->where('status', 'waiting')->count(),
                'in_progress' => Encounter::query()->where('status', 'in_progress')->count(),
                'opd' => Encounter::query()->where('type', 'opd')->whereIn('status', ['waiting', 'in_progress'])->count(),
                'emergency' => Encounter::query()->where('type', 'emergency')->whereIn('status', ['waiting', 'in_progress'])->count(),
            ],
            'billing' => [
                'draft' => Invoice::query()->where('status', 'draft')->count(),
                'issued' => Invoice::query()->where('status', 'issued')->count(),
                'paid' => Invoice::query()->where('status', 'paid')->count(),
                'total' => Invoice::query()->sum('total'),
            ],
            'referrals' => [
                'total' => (clone $referralQuery)->count(),
                'pending' => (clone $referralQuery)->where('status', 'pending')->count(),
                'accepted' => (clone $referralQuery)->where('status', 'accepted')->count(),
                'in_transit' => (clone $referralQuery)->where('status', 'in_transit')->count(),
                'incoming' => $user->hospital_id
                    ? (clone $referralQuery)->where('to_hospital_id', $user->hospital_id)->where('status', 'pending')->count()
                    : (clone $referralQuery)->where('status', 'pending')->count(),
            ],
            'assistance' => [
                'total' => (clone $assistanceQuery)->count(),
                'pending' => (clone $assistanceQuery)->where('status', 'pending')->count(),
                'accepted' => (clone $assistanceQuery)->where('status', 'accepted')->count(),
            ],
            'ambulances' => [
                'total' => (clone $ambulanceQuery)->count(),
                'available' => (clone $ambulanceQuery)->where('status', 'available')->count(),
                'on_trip' => (clone $ambulanceQuery)->where('status', 'on_trip')->count(),
                'maintenance' => (clone $ambulanceQuery)->where('status', 'maintenance')->count(),
            ],
        ]);
    }
}
