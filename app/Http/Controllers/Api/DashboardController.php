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
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $facilityRow = Facility::query()
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
                SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved,
                SUM(CASE WHEN status = 'unavailable' THEN 1 ELSE 0 END) as unavailable,
                SUM(capacity) as capacity,
                SUM(current_utilization) as utilization
            ")
            ->first();

        $patientCounts = $this->countsBy(Patient::query(), 'status');
        $encounterStatus = $this->countsBy(Encounter::query(), 'status');
        $openByType = Encounter::query()
            ->select('type', DB::raw('COUNT(*) as aggregate'))
            ->whereIn('status', ['waiting', 'in_progress'])
            ->groupBy('type')
            ->pluck('aggregate', 'type');
        $invoiceCounts = $this->countsBy(Invoice::query(), 'status');
        $referralCounts = $this->countsBy(Referral::query(), 'status');
        $assistanceCounts = $this->countsBy(AssistanceRequest::query(), 'status');
        $ambulanceCounts = $this->countsBy(Ambulance::query(), 'status');

        $incomingPending = $user->hospital_id
            ? Referral::query()->where('to_hospital_id', $user->hospital_id)->where('status', 'pending')->count()
            : (int) ($referralCounts['pending'] ?? 0);

        return response()->json([
            'hospital' => $user->isPlatformAdmin()
                ? ['id' => null, 'name' => 'Network', 'code' => 'NET']
                : $user->hospital,
            'networkHospitals' => $user->isPlatformAdmin() ? Hospital::query()->where('is_active', true)->count() : null,
            'facilities' => [
                'total' => (int) ($facilityRow->total ?? 0),
                'available' => (int) ($facilityRow->available ?? 0),
                'occupied' => (int) ($facilityRow->occupied ?? 0),
                'maintenance' => (int) ($facilityRow->maintenance ?? 0),
                'reserved' => (int) ($facilityRow->reserved ?? 0),
                'unavailable' => (int) ($facilityRow->unavailable ?? 0),
                'capacity' => (int) ($facilityRow->capacity ?? 0),
                'utilization' => (int) ($facilityRow->utilization ?? 0),
            ],
            'facilitiesByType' => Facility::query()
                ->selectRaw('facility_type_id, count(*) as total, sum(capacity) as capacity, sum(current_utilization) as utilization')
                ->with('type:id,name,slug,icon')
                ->groupBy('facility_type_id')
                ->get(),
            'patients' => [
                'total' => array_sum($patientCounts),
                'active' => array_sum(array_filter($patientCounts, fn ($count, $status) => ! in_array($status, ['discharged', 'deceased'], true), ARRAY_FILTER_USE_BOTH)),
                'admitted' => (int) ($patientCounts['admitted'] ?? 0),
            ],
            'encounters' => [
                'waiting' => (int) ($encounterStatus['waiting'] ?? 0),
                'in_progress' => (int) ($encounterStatus['in_progress'] ?? 0),
                'opd' => (int) ($openByType['opd'] ?? 0),
                'emergency' => (int) ($openByType['emergency'] ?? 0),
            ],
            'billing' => [
                'draft' => (int) ($invoiceCounts['draft'] ?? 0),
                'issued' => (int) ($invoiceCounts['issued'] ?? 0),
                'paid' => (int) ($invoiceCounts['paid'] ?? 0),
                'total' => (int) Invoice::query()->sum('total'),
            ],
            'referrals' => [
                'total' => array_sum($referralCounts),
                'pending' => (int) ($referralCounts['pending'] ?? 0),
                'accepted' => (int) ($referralCounts['accepted'] ?? 0),
                'in_transit' => (int) ($referralCounts['in_transit'] ?? 0),
                'incoming' => $incomingPending,
            ],
            'assistance' => [
                'total' => array_sum($assistanceCounts),
                'pending' => (int) ($assistanceCounts['pending'] ?? 0),
                'accepted' => (int) ($assistanceCounts['accepted'] ?? 0),
            ],
            'ambulances' => [
                'total' => array_sum($ambulanceCounts),
                'available' => (int) ($ambulanceCounts['available'] ?? 0),
                'on_trip' => (int) ($ambulanceCounts['on_trip'] ?? 0),
                'maintenance' => (int) ($ambulanceCounts['maintenance'] ?? 0),
            ],
        ]);
    }

    private function countsBy($query, string $column): array
    {
        return (clone $query)
            ->select($column, DB::raw('COUNT(*) as aggregate'))
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->map(fn ($value) => (int) $value)
            ->all();
    }
}
