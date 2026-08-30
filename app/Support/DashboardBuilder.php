<?php

namespace App\Support;

use App\Models\Ambulance;
use App\Models\AmbulanceTrip;
use App\Models\AssistanceRequest;
use App\Models\AuditEvent;
use App\Models\BedAssignment;
use App\Models\Department;
use App\Models\Encounter;
use App\Models\EncounterClinician;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Referral;
use App\Models\ServiceOrder;
use App\Models\User;

class DashboardBuilder
{
    private ?array $occupancyCache = null;

    public function __construct(private User $user) {}

    public function payload(): array
    {
        if ($this->user->isPlatformAdmin()) {
            return $this->platform();
        }

        $panels = $this->panels();
        $kpis = $this->kpis($panels);
        $tasks = $this->tasks($panels);
        $alerts = $this->alerts($panels);

        return [
            'role' => $this->user->role?->slug,
            'workspace' => $this->user->role?->workspace,
            'hospital' => $this->user->hospital?->only(['id', 'name']),
            'generated_at' => now()->toIso8601String(),
            'panels' => $panels,
            'kpis' => $kpis,
            'charts' => [
                'encounters' => in_array('encounters_chart', $panels, true) ? $this->encounterChart() : [],
                'occupancy' => in_array('occupancy', $panels, true) ? $this->occupancyBars() : [],
            ],
            'occupancy' => in_array('occupancy', $panels, true) ? $this->occupancy() : null,
            'tasks' => $tasks,
            'alerts' => $alerts,
            'encounters' => in_array('encounters', $panels, true) ? $this->recentEncounters() : [],
            'mine' => in_array('mine', $panels, true) ? $this->myEncounters() : [],
            'patients' => in_array('patients', $panels, true) ? $this->recentPatients() : [],
            'admissions' => in_array('admissions', $panels, true) ? $this->movements('admitted_at') : [],
            'discharges' => in_array('admissions', $panels, true) ? $this->movements('discharged_at') : [],
            'transfers' => in_array('admissions', $panels, true) ? $this->transfers() : [],
            'departments' => in_array('departments', $panels, true) ? $this->departmentActivity() : [],
            'laboratory' => in_array('laboratory', $panels, true) ? $this->orders('laboratory') : [],
            'imaging' => in_array('imaging', $panels, true) ? $this->orders('imaging') : [],
            'theatre' => in_array('theatre', $panels, true) ? $this->orders('theatre') : [],
            'pharmacy' => in_array('pharmacy', $panels, true) ? $this->prescriptions() : [],
            'emergency' => in_array('emergency', $panels, true) ? $this->emergencyQueue() : [],
            'referrals' => in_array('referrals', $panels, true) ? $this->referrals() : [],
            'assistance' => in_array('assistance', $panels, true) ? $this->assistance() : [],
            'ambulances' => in_array('ambulances', $panels, true) ? $this->ambulanceBoard() : [],
            'billing' => in_array('billing', $panels, true) ? $this->billing() : null,
            'activity' => in_array('activity', $panels, true) ? $this->activity() : [],
        ];
    }

    private function platform(): array
    {
        return [
            'role' => 'platform-admin',
            'workspace' => $this->user->role?->workspace,
            'hospital' => ['id' => null, 'name' => 'Network operations'],
            'generated_at' => now()->toIso8601String(),
            'panels' => ['kpis', 'activity'],
            'kpis' => [
                $this->kpi('hospitals', 'Hospitals', Hospital::query()->where('is_active', true)->count(), 'Active network sites', 'hospital', 'admin-hospitals'),
                $this->kpi('users', 'Directory', User::query()->count(), 'Accounts across the network', 'users', 'admin-users'),
            ],
            'charts' => ['encounters' => [], 'occupancy' => []],
            'occupancy' => null,
            'tasks' => [],
            'alerts' => [],
            'encounters' => [],
            'mine' => [],
            'patients' => [],
            'admissions' => [],
            'discharges' => [],
            'transfers' => [],
            'departments' => [],
            'laboratory' => [],
            'imaging' => [],
            'theatre' => [],
            'pharmacy' => [],
            'emergency' => [],
            'referrals' => [],
            'assistance' => [],
            'ambulances' => [],
            'billing' => null,
            'activity' => $this->activity(),
        ];
    }

    private function panels(): array
    {
        $panels = ['kpis', 'tasks', 'alerts'];

        if ($this->can('Patient') || $this->can('Opd') || $this->can('Emergency') || $this->can('Reception') || $this->can('Ward')) {
            $panels[] = 'encounters_chart';
            $panels[] = 'encounters';
        }

        if ($this->can('Patient')) {
            $panels[] = 'patients';
        }

        if ($this->can('Bed') || $this->can('Ward') || $this->can('Report')) {
            $panels[] = 'occupancy';
        }

        if ($this->can('Ward') || $this->can('Bed') || $this->can('Opd') || $this->can('Emergency')) {
            $panels[] = 'admissions';
        }

        if ($this->can('Laboratory')) {
            $panels[] = 'laboratory';
        }

        if ($this->can('Imaging')) {
            $panels[] = 'imaging';
        }

        if ($this->can('Theatre')) {
            $panels[] = 'theatre';
        }

        if ($this->can('Pharmacy')) {
            $panels[] = 'pharmacy';
        }

        if ($this->can('Emergency')) {
            $panels[] = 'emergency';
        }

        if ($this->can('Referral')) {
            $panels[] = 'referrals';
        }

        if ($this->can('AssistanceRequest')) {
            $panels[] = 'assistance';
        }

        if ($this->can('Ambulance')) {
            $panels[] = 'ambulances';
        }

        if ($this->can('Invoice')) {
            $panels[] = 'billing';
        }

        if ($this->can('Opd') || $this->can('Emergency') || $this->can('Ward') || $this->can('Reception')) {
            $panels[] = 'mine';
        }

        if ($this->can('Report') || $this->can('User')) {
            $panels[] = 'activity';
        }

        if ($this->can('Report') || $this->can('Department')) {
            $panels[] = 'departments';
        }

        return $panels;
    }

    private function kpis(array $panels): array
    {
        $items = [];

        if (in_array('occupancy', $panels, true)) {
            $row = Facility::query()
                ->whereHas('type', fn ($query) => $query->where('slug', 'bed'))
                ->selectRaw('COALESCE(SUM(capacity),0) as capacity, COALESCE(SUM(current_utilization),0) as used')
                ->first();
            $capacity = (int) ($row->capacity ?? 0);
            $used = (int) ($row->used ?? 0);
            $percent = $capacity ? (int) round(($used / $capacity) * 100) : 0;
            $items[] = $this->kpi(
                'occupancy',
                'Bed occupancy',
                $percent.'%',
                $used.' of '.$capacity.' beds in use',
                'bed',
                'beds',
                $percent >= 90 ? 'danger' : ($percent >= 80 ? 'warn' : null),
                $this->trend(
                    BedAssignment::query()->where('status', 'active')->whereDate('assigned_at', today())->count(),
                    BedAssignment::query()->where('status', 'active')->whereDate('assigned_at', today()->subDay())->count()
                )
            );
        }

        if (in_array('encounters', $panels, true) || in_array('encounters_chart', $panels, true)) {
            $open = Encounter::query()->whereIn('status', ['waiting', 'in_progress'])->count();
            $items[] = $this->kpi(
                'encounters',
                'Open encounters',
                $open,
                'Waiting and in progress',
                'stethoscope',
                $this->user->role?->workspace && $this->user->role->workspace !== 'admin' ? $this->user->role->workspace : 'reception',
                $open >= 20 ? 'warn' : null,
                $this->trend(
                    Encounter::query()->whereDate('created_at', today())->count(),
                    Encounter::query()->whereDate('created_at', today()->subDay())->count()
                )
            );
        }

        if (in_array('patients', $panels, true)) {
            $active = Patient::query()->whereNotIn('status', ['discharged', 'deceased'])->count();
            $items[] = $this->kpi('patients', 'Active patients', $active, 'Currently under care', 'users', 'patients');
        }

        if (in_array('emergency', $panels, true)) {
            $er = Encounter::query()->where('type', 'emergency')->whereIn('status', ['waiting', 'in_progress'])->count();
            $items[] = $this->kpi('emergency', 'Emergency board', $er, 'Active emergency visits', 'emergency', 'emergency', $er >= 8 ? 'warn' : null);
        }

        if (in_array('laboratory', $panels, true)) {
            $lab = ServiceOrder::query()->where('module_key', 'laboratory')->whereIn('status', ['requested', 'collected', 'processing'])->count();
            $items[] = $this->kpi('laboratory', 'Lab queue', $lab, 'Orders awaiting results', 'flask', 'laboratory', $lab >= 15 ? 'warn' : null);
        }

        if (in_array('pharmacy', $panels, true)) {
            $rx = Prescription::query()->whereIn('status', ['pending', 'verified'])->count();
            $items[] = $this->kpi('pharmacy', 'Pharmacy queue', $rx, 'Prescriptions to verify or dispense', 'pill', 'pharmacy', $rx >= 15 ? 'warn' : null);
        }

        if (in_array('referrals', $panels, true)) {
            $incoming = Referral::query()->where('to_hospital_id', $this->user->hospital_id)->where('status', 'pending')->count();
            $items[] = $this->kpi('referrals', 'Pending referrals', $incoming, 'Incoming transfers to accept', 'transfer', 'referrals', $incoming ? 'warn' : null);
        }

        if (in_array('ambulances', $panels, true)) {
            $ready = Ambulance::query()->where('status', 'available')->count();
            $items[] = $this->kpi('ambulances', 'Ambulances ready', $ready, 'Vehicles available to dispatch', 'ambulance', 'ambulances', $ready === 0 ? 'warn' : null);
        }

        if (in_array('billing', $panels, true)) {
            $open = Invoice::query()->whereIn('status', ['draft', 'issued'])->count();
            $items[] = $this->kpi('billing', 'Open invoices', $open, 'Draft and issued bills', 'receipt', 'billing');
        }

        if (in_array('mine', $panels, true)) {
            $mine = $this->myEncounterQuery()->count();
            $items[] = $this->kpi('mine', 'Assigned to me', $mine, 'Open encounters on your list', 'stethoscope', $this->user->role?->workspace ?: 'opd');
        }

        return array_slice($items, 0, 8);
    }

    private function tasks(array $panels): array
    {
        $items = [];

        if (in_array('encounters', $panels, true)) {
            $waiting = Encounter::query()->where('status', 'waiting')->count();
            if ($waiting) {
                $items[] = ['title' => 'Waiting encounters', 'count' => $waiting, 'to' => 'reception', 'tone' => 'warning'];
            }
        }

        if (in_array('emergency', $panels, true)) {
            $er = Encounter::query()->where('type', 'emergency')->where('status', 'waiting')->count();
            if ($er) {
                $items[] = ['title' => 'Emergency waiting', 'count' => $er, 'to' => 'emergency', 'tone' => 'error'];
            }
        }

        if (in_array('laboratory', $panels, true)) {
            $lab = ServiceOrder::query()->where('module_key', 'laboratory')->whereIn('status', ['requested', 'collected', 'processing'])->count();
            if ($lab) {
                $items[] = ['title' => 'Laboratory orders in queue', 'count' => $lab, 'to' => 'laboratory', 'tone' => 'warning'];
            }
        }

        if (in_array('imaging', $panels, true)) {
            $imaging = ServiceOrder::query()->where('module_key', 'imaging')->whereIn('status', ['requested', 'scheduled', 'processing'])->count();
            if ($imaging) {
                $items[] = ['title' => 'Imaging studies pending', 'count' => $imaging, 'to' => 'imaging', 'tone' => 'warning'];
            }
        }

        if (in_array('pharmacy', $panels, true)) {
            $rx = Prescription::query()->whereIn('status', ['pending', 'verified'])->count();
            if ($rx) {
                $items[] = ['title' => 'Prescriptions awaiting pharmacy', 'count' => $rx, 'to' => 'pharmacy', 'tone' => 'warning'];
            }
        }

        if (in_array('theatre', $panels, true)) {
            $theatre = ServiceOrder::query()->where('module_key', 'theatre')->whereIn('status', ['requested', 'scheduled', 'processing'])->count();
            if ($theatre) {
                $items[] = ['title' => 'Theatre cases pending', 'count' => $theatre, 'to' => 'theatre', 'tone' => 'warning'];
            }
        }

        if (in_array('referrals', $panels, true)) {
            $incoming = Referral::query()->where('to_hospital_id', $this->user->hospital_id)->where('status', 'pending')->count();
            if ($incoming) {
                $items[] = ['title' => 'Incoming referrals to review', 'count' => $incoming, 'to' => 'referrals', 'tone' => 'warning'];
            }
        }

        if (in_array('assistance', $panels, true)) {
            $help = AssistanceRequest::query()->where('status', 'pending')->count();
            if ($help) {
                $items[] = ['title' => 'Assistance requests open', 'count' => $help, 'to' => 'assistance', 'tone' => 'warning'];
            }
        }

        if (in_array('ambulances', $panels, true)) {
            $trips = AmbulanceTrip::query()->whereIn('status', ['dispatched', 'en_route', 'arrived'])->count();
            if ($trips) {
                $items[] = ['title' => 'Active ambulance trips', 'count' => $trips, 'to' => 'ambulances', 'tone' => 'info'];
            }
        }

        if (in_array('billing', $panels, true)) {
            $bills = Invoice::query()->where('status', 'issued')->count();
            if ($bills) {
                $items[] = ['title' => 'Invoices awaiting payment', 'count' => $bills, 'to' => 'billing', 'tone' => 'warning'];
            }
        }

        if (in_array('mine', $panels, true)) {
            $mine = $this->myEncounterQuery()->count();
            if ($mine) {
                $items[] = ['title' => 'Encounters assigned to you', 'count' => $mine, 'to' => $this->user->role?->workspace ?: 'opd', 'tone' => 'info'];
            }
        }

        return array_slice($items, 0, 8);
    }

    private function alerts(array $panels): array
    {
        $items = [];

        if (in_array('occupancy', $panels, true)) {
            $row = Facility::query()
                ->whereHas('type', fn ($query) => $query->where('slug', 'bed'))
                ->selectRaw('COALESCE(SUM(capacity),0) as capacity, COALESCE(SUM(current_utilization),0) as used')
                ->first();
            $capacity = (int) ($row->capacity ?? 0);
            $used = (int) ($row->used ?? 0);
            if ($capacity && ($used / $capacity) >= 0.9) {
                $items[] = ['title' => 'Bed capacity is critical', 'detail' => $used.' of '.$capacity.' beds are occupied.', 'tone' => 'error', 'to' => 'beds'];
            } elseif ($capacity && ($used / $capacity) >= 0.8) {
                $items[] = ['title' => 'Bed occupancy is high', 'detail' => $used.' of '.$capacity.' beds are occupied.', 'tone' => 'warning', 'to' => 'beds'];
            }
        }

        if (in_array('laboratory', $panels, true)) {
            $stale = ServiceOrder::query()
                ->where('module_key', 'laboratory')
                ->whereIn('status', ['requested', 'collected', 'processing'])
                ->where('requested_at', '<=', now()->subHours(24))
                ->count();
            if ($stale) {
                $items[] = ['title' => 'Laboratory results overdue', 'detail' => $stale.' order'.($stale === 1 ? '' : 's').' older than 24 hours.', 'tone' => 'warning', 'to' => 'laboratory'];
            }
        }

        if (in_array('ambulances', $panels, true) && Ambulance::query()->exists() && ! Ambulance::query()->where('status', 'available')->exists()) {
            $items[] = ['title' => 'No ambulance is available', 'detail' => 'Every vehicle is on a trip, in maintenance, or unavailable.', 'tone' => 'warning', 'to' => 'ambulances'];
        }

        if (in_array('emergency', $panels, true)) {
            $waiting = Encounter::query()->where('type', 'emergency')->where('status', 'waiting')->count();
            if ($waiting >= 5) {
                $items[] = ['title' => 'Emergency waiting list is building', 'detail' => $waiting.' patients are waiting in emergency.', 'tone' => 'error', 'to' => 'emergency'];
            }
        }

        return $items;
    }

    private function encounterChart(): array
    {
        $start = now()->subDays(6)->startOfDay();
        $rows = Encounter::query()
            ->where('created_at', '>=', $start)
            ->selectRaw("date(created_at) as day, COUNT(*) as aggregate")
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $series[] = [
                'label' => now()->subDays($i)->format('D'),
                'value' => (int) ($rows[$day] ?? 0),
            ];
        }

        return $series;
    }

    private function occupancy(): array
    {
        if ($this->occupancyCache !== null) {
            return $this->occupancyCache;
        }

        $row = Facility::query()
            ->whereHas('type', fn ($query) => $query->where('slug', 'bed'))
            ->selectRaw('COALESCE(SUM(capacity),0) as capacity, COALESCE(SUM(current_utilization),0) as used')
            ->first();
        $capacity = (int) ($row->capacity ?? 0);
        $used = (int) ($row->used ?? 0);

        $wards = Facility::query()
            ->whereHas('type', fn ($query) => $query->where('slug', 'ward'))
            ->orderBy('name')
            ->get(['id', 'name', 'capacity', 'current_utilization', 'status'])
            ->map(fn (Facility $ward) => [
                'id' => $ward->id,
                'name' => $ward->name,
                'capacity' => (int) $ward->capacity,
                'used' => (int) $ward->current_utilization,
                'remaining' => max(0, (int) $ward->capacity - (int) $ward->current_utilization),
                'status' => $ward->status,
                'to' => ['name' => 'wards'],
            ])
            ->all();

        return $this->occupancyCache = [
            'capacity' => $capacity,
            'used' => $used,
            'remaining' => max(0, $capacity - $used),
            'percent' => $capacity ? (int) round(($used / $capacity) * 100) : 0,
            'wards' => $wards,
        ];
    }

    private function occupancyBars(): array
    {
        return collect($this->occupancy()['wards'] ?? [])->map(fn (array $ward) => [
            'label' => $ward['name'],
            'value' => $ward['used'],
            'max' => max($ward['capacity'], $ward['used'], 1),
        ])->take(6)->values()->all();
    }

    private function recentEncounters(): array
    {
        return Encounter::query()
            ->with(['patient:id,mrn,first_name,last_name,status', 'department:id,name'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Encounter $encounter) => $this->encounterRow($encounter))
            ->all();
    }

    private function myEncounters(): array
    {
        return $this->myEncounterQuery()
            ->with(['patient:id,mrn,first_name,last_name,status', 'department:id,name'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Encounter $encounter) => $this->encounterRow($encounter))
            ->all();
    }

    private function myEncounterQuery()
    {
        $userId = $this->user->id;

        return Encounter::query()
            ->whereIn('status', ['waiting', 'in_progress'])
            ->where(function ($query) use ($userId) {
                $query->where('clinician_id', $userId)
                    ->orWhereIn('id', EncounterClinician::query()->select('encounter_id')->where('user_id', $userId));
            });
    }

    private function recentPatients(): array
    {
        return Patient::query()
            ->latest()
            ->limit(8)
            ->get(['id', 'mrn', 'first_name', 'last_name', 'status', 'updated_at'])
            ->map(fn (Patient $patient) => [
                'id' => $patient->id,
                'name' => trim($patient->first_name.' '.$patient->last_name),
                'mrn' => $patient->mrn,
                'status' => $patient->status,
                'at' => $patient->updated_at,
                'to' => ['name' => 'patients-id', 'params' => ['id' => $patient->id]],
            ])
            ->all();
    }

    private function movements(string $column): array
    {
        return Encounter::query()
            ->with(['patient:id,mrn,first_name,last_name,status'])
            ->whereNotNull($column)
            ->latest($column)
            ->limit(6)
            ->get()
            ->map(fn (Encounter $encounter) => [
                ...$this->encounterRow($encounter),
                'at' => $encounter->{$column},
            ])
            ->all();
    }

    private function transfers(): array
    {
        return Encounter::query()
            ->with(['patient:id,mrn,first_name,last_name,status', 'department:id,name'])
            ->where(function ($query) {
                $query->where('status', 'transferred')->orWhere('type', 'referral');
            })
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Encounter $encounter) => $this->encounterRow($encounter))
            ->all();
    }

    private function departmentActivity(): array
    {
        $counts = Encounter::query()
            ->whereIn('status', ['waiting', 'in_progress'])
            ->whereNotNull('department_id')
            ->selectRaw('department_id, COUNT(*) as aggregate')
            ->groupBy('department_id')
            ->pluck('aggregate', 'department_id');

        if ($counts->isEmpty()) {
            return [];
        }

        return Department::query()
            ->whereIn('id', $counts->keys())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $department) => [
                'id' => $department->id,
                'label' => $department->name,
                'value' => (int) $counts[$department->id],
            ])
            ->all();
    }

    private function orders(string $module): array
    {
        $statuses = $module === 'imaging'
            ? ['requested', 'scheduled', 'processing']
            : ['requested', 'collected', 'scheduled', 'processing'];

        return ServiceOrder::query()
            ->with(['patient:id,mrn,first_name,last_name,status'])
            ->where('module_key', $module)
            ->whereIn('status', $statuses)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (ServiceOrder $order) => [
                'id' => $order->id,
                'name' => $order->patient ? trim($order->patient->first_name.' '.$order->patient->last_name) : 'Patient',
                'mrn' => $order->patient?->mrn,
                'item' => $order->item_name,
                'status' => $order->status,
                'at' => $order->requested_at ?: $order->created_at,
                'to' => $order->patient_id ? ['name' => 'patients-id', 'params' => ['id' => $order->patient_id]] : null,
            ])
            ->all();
    }

    private function prescriptions(): array
    {
        return Prescription::query()
            ->with(['patient:id,mrn,first_name,last_name,status'])
            ->whereIn('status', ['pending', 'verified'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Prescription $prescription) => [
                'id' => $prescription->id,
                'name' => $prescription->patient ? trim($prescription->patient->first_name.' '.$prescription->patient->last_name) : 'Patient',
                'mrn' => $prescription->patient?->mrn,
                'status' => $prescription->status,
                'at' => $prescription->prescribed_at ?: $prescription->created_at,
                'to' => $prescription->patient_id ? ['name' => 'patients-id', 'params' => ['id' => $prescription->patient_id]] : null,
            ])
            ->all();
    }

    private function emergencyQueue(): array
    {
        return Encounter::query()
            ->with(['patient:id,mrn,first_name,last_name,status', 'department:id,name'])
            ->where('type', 'emergency')
            ->whereIn('status', ['waiting', 'in_progress'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Encounter $encounter) => $this->encounterRow($encounter))
            ->all();
    }

    private function referrals(): array
    {
        return Referral::query()
            ->with(['patient:id,mrn,first_name,last_name,status', 'fromHospital:id,name', 'toHospital:id,name'])
            ->whereIn('status', ['pending', 'in_transit'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Referral $referral) => [
                'id' => $referral->id,
                'name' => $referral->patient ? trim($referral->patient->first_name.' '.$referral->patient->last_name) : 'Patient',
                'mrn' => $referral->patient?->mrn,
                'from' => $referral->fromHospital?->name,
                'to_hospital' => $referral->toHospital?->name,
                'status' => $referral->status,
                'to' => ['name' => 'referrals-id', 'params' => ['id' => $referral->id]],
            ])
            ->all();
    }

    private function assistance(): array
    {
        return AssistanceRequest::query()
            ->whereIn('status', ['pending', 'accepted'])
            ->latest()
            ->limit(6)
            ->get(['id', 'title', 'type', 'status', 'created_at'])
            ->map(fn (AssistanceRequest $request) => [
                'id' => $request->id,
                'title' => $request->title,
                'type' => $request->type,
                'status' => $request->status,
                'at' => $request->created_at,
                'to' => ['name' => 'assistance-id', 'params' => ['id' => $request->id]],
            ])
            ->all();
    }

    private function ambulanceBoard(): array
    {
        $trips = AmbulanceTrip::query()
            ->with(['ambulance:id,vehicle_code,status', 'patient:id,mrn,first_name,last_name'])
            ->whereIn('status', ['dispatched', 'en_route', 'arrived'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (AmbulanceTrip $trip) => [
                'id' => $trip->id,
                'vehicle' => $trip->ambulance?->vehicle_code,
                'name' => $trip->patient ? trim($trip->patient->first_name.' '.$trip->patient->last_name) : 'Unassigned',
                'status' => $trip->status,
                'destination' => $trip->destination,
                'to' => $trip->ambulance_id ? ['name' => 'ambulances-id', 'params' => ['id' => $trip->ambulance_id]] : ['name' => 'ambulances'],
            ]);

        return [
            'available' => Ambulance::query()->where('status', 'available')->count(),
            'on_trip' => Ambulance::query()->where('status', 'on_trip')->count(),
            'trips' => $trips->all(),
        ];
    }

    private function billing(): array
    {
        return [
            'draft' => Invoice::query()->where('status', 'draft')->count(),
            'issued' => Invoice::query()->where('status', 'issued')->count(),
            'paid' => Invoice::query()->where('status', 'paid')->count(),
            'outstanding' => (int) Invoice::query()->where('status', 'issued')->sum('total'),
            'collected' => (int) Invoice::query()->where('status', 'paid')->whereDate('paid_at', today())->sum('total'),
            'invoices' => Invoice::query()
                ->with('patient:id,mrn,first_name,last_name,status')
                ->whereIn('status', ['draft', 'issued'])
                ->latest()
                ->limit(6)
                ->get()
                ->map(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'name' => $invoice->patient ? trim($invoice->patient->first_name.' '.$invoice->patient->last_name) : 'Patient',
                    'status' => $invoice->status,
                    'total' => $invoice->total,
                    'to' => ['name' => 'billing'],
                ])
                ->all(),
        ];
    }

    private function activity(): array
    {
        $query = AuditEvent::query()->with('hospital:id,name')->latest('created_at')->limit(8);

        if (! $this->user->isPlatformAdmin()) {
            $query->where(function ($builder) {
                $builder->where('hospital_id', $this->user->hospital_id)->orWhereNull('hospital_id');
            });
        }

        return $query->get()->map(fn (AuditEvent $event) => [
            'id' => $event->id,
            'action' => $event->action,
            'entity' => class_basename((string) $event->auditable_type),
            'hospital' => $event->hospital?->name,
            'at' => $event->created_at,
        ])->all();
    }

    private function encounterRow(Encounter $encounter): array
    {
        return [
            'id' => $encounter->id,
            'name' => $encounter->patient ? trim($encounter->patient->first_name.' '.$encounter->patient->last_name) : 'Patient',
            'mrn' => $encounter->patient?->mrn,
            'type' => $encounter->type,
            'status' => $encounter->status,
            'department' => $encounter->department?->name,
            'at' => $encounter->started_at ?: $encounter->created_at,
            'to' => $encounter->patient_id ? ['name' => 'patients-id', 'params' => ['id' => $encounter->patient_id]] : null,
        ];
    }

    private function kpi(string $key, string $title, int|string $value, string $hint, string $icon, string $to, ?string $tone = null, ?int $trend = null): array
    {
        return compact('key', 'title', 'value', 'hint', 'icon', 'to', 'tone', 'trend');
    }

    private function trend(int $today, int $yesterday): ?int
    {
        if (! $yesterday && ! $today) {
            return 0;
        }

        if (! $yesterday) {
            return $today ? 100 : 0;
        }

        return (int) round((($today - $yesterday) / $yesterday) * 100);
    }

    private function can(string $subject): bool
    {
        return $this->user->hasPermission('read', $subject);
    }
}
