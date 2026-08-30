<?php

namespace App\Support\Reports;

use App\Models\Ambulance;
use App\Models\AmbulanceTrip;
use App\Models\AssistanceRequest;
use App\Models\BedAssignment;
use App\Models\Department;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Referral;
use App\Models\ServiceOrder;
use App\Models\StaffAssignment;
use App\Models\User;
use App\Support\Access;
use App\Support\HospitalSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportQuery
{
    public function __construct(
        public User $user,
        public ReportCriteria $criteria,
    ) {}

    public function applyDate(Builder $query, string $column): Builder
    {
        return $query
            ->where($column, '>=', $this->criteria->from)
            ->where($column, '<=', $this->criteria->to);
    }

    public function applyPrevious(Builder $query, string $column): Builder
    {
        return $query
            ->where($column, '>=', $this->criteria->previousFrom())
            ->where($column, '<=', $this->criteria->previousTo());
    }

    public function applyStatus(Builder $query, string $column = 'status'): Builder
    {
        if ($this->criteria->status) {
            $query->where($column, $this->criteria->status);
        }

        return $query;
    }

    public function applyDepartment(Builder $query, string $column = 'department_id'): Builder
    {
        if ($this->criteria->departmentId) {
            $query->where($column, $this->criteria->departmentId);
        }

        return $query;
    }

    public function applyFacility(Builder $query, string $column = 'facility_id'): Builder
    {
        if ($this->criteria->facilityId) {
            $query->where($column, $this->criteria->facilityId);
        }

        return $query;
    }

    public function applyClinician(Builder $query, string $column = 'clinician_id'): Builder
    {
        if ($this->criteria->clinicianId) {
            $query->where($column, $this->criteria->clinicianId);
        }

        return $query;
    }

    public function applyKind(Builder $query, string $column): Builder
    {
        if ($this->criteria->kind) {
            $query->where($column, $this->criteria->kind);
        }

        return $query;
    }

    public function encounterScope(?string $type = null, ?array $types = null, bool $previous = false): Builder
    {
        $query = Access::encounterQuery($this->user, Encounter::query(), $type);
        if ($type) {
            $query->where('type', $type);
        }
        if ($types) {
            $query->whereIn('type', $types);
        }

        $previous ? $this->applyPrevious($query, 'created_at') : $this->applyDate($query, 'created_at');
        $this->applyDepartment($query);
        $this->applyFacility($query);
        $this->applyClinician($query);
        $this->applyStatus($query);
        if ($this->criteria->kind && ! $type) {
            $query->where('type', $this->criteria->kind);
        } elseif ($this->criteria->kind && $types) {
            $query->where('type', $this->criteria->kind);
        }

        return $query;
    }

    public function patients(bool $previous = false): Builder
    {
        $query = Access::patientQuery($this->user, Patient::query());
        $previous ? $this->applyPrevious($query, 'created_at') : $this->applyDate($query, 'created_at');
        $this->applyStatus($query);
        if ($this->criteria->patientType) {
            $query->where('sex', $this->criteria->patientType);
        }

        if ($this->criteria->departmentId || $this->criteria->facilityId || $this->criteria->clinicianId) {
            $encounters = Encounter::query()->select('patient_id');
            $this->applyDepartment($encounters);
            $this->applyFacility($encounters);
            $this->applyClinician($encounters);
            $query->whereIn('id', $encounters);
        }

        return $query;
    }

    public function roster(): Builder
    {
        $query = Access::patientQuery($this->user, Patient::query());
        $this->applyStatus($query);
        if ($this->criteria->patientType) {
            $query->where('sex', $this->criteria->patientType);
        }

        return $query;
    }

    public function orders(string $module, bool $previous = false, string $dateColumn = 'requested_at'): Builder
    {
        $query = ServiceOrder::query()->where('module_key', $module);
        $previous ? $this->applyPrevious($query, $dateColumn) : $this->applyDate($query, $dateColumn);
        $this->applyFacility($query);
        $this->applyClinician($query, 'ordered_by');
        $this->applyStatus($query);

        if ($this->criteria->departmentId) {
            $query->whereIn('encounter_id', Encounter::query()->select('id')->where('department_id', $this->criteria->departmentId));
        }

        return $query;
    }

    public function prescriptions(bool $previous = false): Builder
    {
        $query = Prescription::query();
        $previous ? $this->applyPrevious($query, 'prescribed_at') : $this->applyDate($query, 'prescribed_at');
        $this->applyClinician($query, 'prescribed_by');
        $this->applyStatus($query);

        if ($this->criteria->departmentId) {
            $query->whereIn('encounter_id', Encounter::query()->select('id')->where('department_id', $this->criteria->departmentId));
        }

        return $query;
    }

    public function invoices(bool $previous = false): Builder
    {
        $query = Invoice::query();
        $previous ? $this->applyPrevious($query, 'created_at') : $this->applyDate($query, 'created_at');
        $this->applyStatus($query);

        if ($this->criteria->departmentId) {
            $query->whereIn('encounter_id', Encounter::query()->select('id')->where('department_id', $this->criteria->departmentId));
        }

        return $query;
    }

    public function payments(bool $previous = false): Builder
    {
        $query = Payment::query();
        $previous ? $this->applyPrevious($query, 'received_at') : $this->applyDate($query, 'received_at');
        if ($this->criteria->kind) {
            $query->where('method', $this->criteria->kind);
        }

        return $query;
    }

    public function referrals(bool $previous = false): Builder
    {
        $query = Referral::query();
        $previous ? $this->applyPrevious($query, 'created_at') : $this->applyDate($query, 'created_at');
        $this->applyStatus($query);
        $this->applyClinician($query, 'referring_clinician_id');

        if ($this->criteria->kind === 'outgoing' && $this->user->hospital_id) {
            $query->where('from_hospital_id', $this->user->hospital_id);
        } elseif ($this->criteria->kind === 'incoming' && $this->user->hospital_id) {
            $query->where('to_hospital_id', $this->user->hospital_id);
        }

        if ($this->criteria->departmentId) {
            $query->whereIn('encounter_id', Encounter::query()->select('id')->where('department_id', $this->criteria->departmentId));
        }

        return $query;
    }

    public function assistance(bool $previous = false): Builder
    {
        $query = AssistanceRequest::query();
        $previous ? $this->applyPrevious($query, 'created_at') : $this->applyDate($query, 'created_at');
        $this->applyStatus($query);
        $this->applyFacility($query);
        $this->applyKind($query, 'type');

        return $query;
    }

    public function trips(bool $previous = false): Builder
    {
        $query = AmbulanceTrip::query();
        $previous ? $this->applyPrevious($query, 'dispatched_at') : $this->applyDate($query, 'dispatched_at');
        $this->applyStatus($query);
        $this->applyClinician($query, 'driver_user_id');
        $this->applyFacility($query, 'destination_facility_id');

        return $query;
    }

    public function ambulances(): Builder
    {
        $query = Ambulance::query();
        if ($this->criteria->kind) {
            $query->where('status', $this->criteria->kind);
        }

        return $query;
    }

    public function assignments(bool $previous = false): Builder
    {
        $query = BedAssignment::query();
        $previous ? $this->applyPrevious($query, 'assigned_at') : $this->applyDate($query, 'assigned_at');
        $this->applyStatus($query);
        $this->applyFacility($query);

        if ($this->criteria->departmentId) {
            $query->whereIn('ward_id', Facility::query()->select('id')->where('department_id', $this->criteria->departmentId));
        }

        return $query;
    }

    public function beds(): Builder
    {
        $query = Facility::query()->whereHas('type', fn ($builder) => $builder->where('slug', 'bed'));
        $this->applyDepartment($query);
        $this->applyFacility($query, 'id');
        $this->applyStatus($query);

        return $query;
    }

    public function wards(): Builder
    {
        $query = Facility::query()->whereHas('type', fn ($builder) => $builder->where('slug', 'ward'));
        $this->applyDepartment($query);
        $this->applyFacility($query, 'id');
        $this->applyStatus($query);

        return $query;
    }

    public function theatres(): Builder
    {
        $query = Facility::query()->whereHas('type', fn ($builder) => $builder->where('slug', 'theatre'));
        $this->applyDepartment($query);
        $this->applyFacility($query, 'id');
        $this->applyStatus($query);

        return $query;
    }

    public function medications(): Builder
    {
        return Medication::query();
    }

    public function staffAssignments(): Builder
    {
        $query = StaffAssignment::query();
        $this->applyDepartment($query);
        $this->applyFacility($query);
        $this->applyStatus($query);

        return $query;
    }

    public function staffUsers(): Builder
    {
        $query = User::query()->whereNotNull('hospital_id');
        if ($this->user->hospital_id) {
            $query->where(function (Builder $builder) {
                $builder->where('hospital_id', $this->user->hospital_id)
                    ->orWhereHas('memberships', fn ($memberships) => $memberships->where('hospital_id', $this->user->hospital_id));
            });
        } elseif (! $this->user->isPlatformAdmin()) {
            $query->whereRaw('0 = 1');
        }

        if ($this->criteria->departmentId) {
            $query->where('department_id', $this->criteria->departmentId);
        }

        return $query;
    }

    public function counts(Builder $query, string $column): array
    {
        return (clone $query)
            ->reorder()
            ->select($column, DB::raw('COUNT(*) as aggregate'))
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    public function sum(Builder $query, string $column): int
    {
        return (int) (clone $query)->reorder()->sum($column);
    }

    public function series(Builder $query, string $column): array
    {
        $expr = $this->dateExpr($column);
        $rows = (clone $query)
            ->reorder()
            ->selectRaw($expr.' as bucket, COUNT(*) as aggregate')
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket');

        $series = [];
        $cursor = $this->criteria->from->startOfDay();
        $end = $this->criteria->to->startOfDay();
        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->toDateString();
            $series[] = [
                'label' => $cursor->format('M j'),
                'value' => (int) ($rows[$key] ?? 0),
                'date' => $key,
            ];
            $cursor = $cursor->addDay();
        }

        return $series;
    }

    public function barsFromCounts(array $counts, int $limit = 8): array
    {
        return collect($counts)
            ->map(fn ($value, $key) => [
                'label' => $key === '' || $key === null ? 'Unspecified' : ReportCatalog::label((string) $key),
                'value' => (int) $value,
            ])
            ->sortByDesc('value')
            ->take($limit)
            ->values()
            ->all();
    }

    public function namedBars(array $counts, string $model, string $name = 'name', int $limit = 8): array
    {
        $ids = collect($counts)->keys()->filter()->all();
        $labels = $ids === [] ? collect() : $model::query()->whereIn('id', $ids)->pluck($name, 'id');

        return collect($counts)
            ->map(fn ($value, $id) => [
                'label' => $id ? (string) ($labels[$id] ?? 'Unknown') : 'Unassigned',
                'value' => (int) $value,
            ])
            ->sortByDesc('value')
            ->take($limit)
            ->values()
            ->all();
    }

    public function change(int $current, int $previous): ?int
    {
        if ($previous === 0) {
            return $current > 0 ? 100 : 0;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

    public function percent(int $part, int $whole): int
    {
        return $whole > 0 ? (int) round(($part / $whole) * 100) : 0;
    }

    public function averageInterval(Builder $query, string $start, string $end, string $unit = 'days'): ?float
    {
        $driver = $query->getConnection()->getDriverName();
        $expr = $driver === 'sqlite'
            ? ($unit === 'hours'
                ? 'AVG((julianday('.$end.') - julianday('.$start.')) * 24)'
                : 'AVG(julianday('.$end.') - julianday('.$start.'))')
            : ($unit === 'hours'
                ? 'AVG(TIMESTAMPDIFF(MINUTE, '.$start.', '.$end.') / 60.0)'
                : 'AVG(TIMESTAMPDIFF(HOUR, '.$start.', '.$end.') / 24.0)');

        $value = (clone $query)
            ->reorder()
            ->whereNotNull($start)
            ->whereNotNull($end)
            ->selectRaw($expr.' as average_span')
            ->value('average_span');

        return $value === null ? null : round((float) $value, 1);
    }

    public function ageBands(Builder $query): array
    {
        $now = now();
        $bands = [
            '0-17' => [$now->copy()->subYears(18)->addDay(), $now->copy()],
            '18-39' => [$now->copy()->subYears(40)->addDay(), $now->copy()->subYears(18)],
            '40-64' => [$now->copy()->subYears(65)->addDay(), $now->copy()->subYears(40)],
            '65+' => [now()->subYears(130), $now->copy()->subYears(65)],
        ];

        $items = [];
        foreach ($bands as $label => [$start, $end]) {
            $items[] = [
                'label' => $label,
                'value' => (clone $query)->whereNotNull('date_of_birth')->whereBetween('date_of_birth', [$start->toDateString(), $end->toDateString()])->count(),
            ];
        }

        $unknown = (clone $query)->whereNull('date_of_birth')->count();
        if ($unknown) {
            $items[] = ['label' => 'Unknown', 'value' => $unknown];
        }

        return $items;
    }

    public function departments(): array
    {
        if ($this->user->isPlatformAdmin() && ! $this->user->hospital_id) {
            return Department::query()->orderBy('name')->limit(200)->get(['id', 'name'])->map(fn (Department $row) => [
                'value' => $row->id,
                'title' => $row->name,
            ])->all();
        }

        return Department::query()->orderBy('name')->get(['id', 'name'])->map(fn (Department $row) => [
            'value' => $row->id,
            'title' => $row->name,
        ])->all();
    }

    public function facilities(): array
    {
        return Facility::query()
            ->with('type:id,name,slug')
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'facility_type_id', 'department_id'])
            ->map(fn (Facility $row) => [
                'value' => $row->id,
                'title' => $row->name,
            ])
            ->all();
    }

    public function clinicians(): array
    {
        return $this->staffUsers()
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'job_title'])
            ->map(fn (User $row) => [
                'value' => $row->id,
                'title' => $row->name,
            ])
            ->all();
    }

    public function hospital(): array
    {
        if ($this->user->isPlatformAdmin() && ! $this->user->hospital_id) {
            return ['id' => null, 'name' => 'Network', 'code' => 'NET'];
        }

        $hospital = $this->user->hospital;

        return [
            'id' => $hospital?->id,
            'name' => $hospital?->name,
            'code' => $hospital ? HospitalSequence::prefix($hospital) : 'HMS',
            'city' => $hospital?->city,
            'region' => $hospital?->region,
            'address' => $hospital?->address,
            'phone' => $hospital?->phone,
        ];
    }

    public function patientName(Patient|array|null $patient): string
    {
        if (! $patient) {
            return 'Patient';
        }

        $first = is_array($patient) ? ($patient['first_name'] ?? '') : $patient->first_name;
        $last = is_array($patient) ? ($patient['last_name'] ?? '') : $patient->last_name;

        return trim($first.' '.$last) ?: 'Patient';
    }

    private function dateExpr(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'sqlite' ? 'date('.$column.')' : 'DATE('.$column.')';
    }
}
