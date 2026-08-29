<?php

namespace App\Support\Reports;

use App\Models\Ambulance;
use App\Models\AmbulanceTrip;
use App\Models\AssistanceRequest;
use App\Models\BedAssignment;
use App\Models\Department;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Referral;
use App\Models\Role;
use App\Models\ServiceOrder;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ReportBuilder
{
    private ReportQuery $query;

    public function __construct(
        private User $user,
        private ReportCriteria $criteria,
    ) {
        $this->query = new ReportQuery($user, $criteria);
    }

    public static function meta(User $user): array
    {
        $probe = new ReportQuery($user, ReportCriteria::fromRequest(request()));

        return [
            'hospital' => $probe->hospital(),
            'tabs' => ReportCatalog::tabs($user),
            'options' => [
                'departments' => $probe->departments(),
                'facilities' => $probe->facilities(),
                'clinicians' => $probe->clinicians(),
            ],
            'schemas' => collect(ReportCatalog::sections())
                ->filter(fn ($_, $key) => ReportCatalog::allows($user, $key))
                ->map(fn ($_, $key) => ReportCatalog::schema($key))
                ->all(),
        ];
    }

    public function payload(): array
    {
        $this->guard();
        $body = $this->section();
        $frame = $this->frame();

        if ($this->criteria->section === 'overview') {
            $frame = array_merge($frame, ReportSnapshot::make($this->user));
        }

        return array_merge($frame, $body);
    }

    public function table(): array
    {
        $this->guard();

        return $this->tableFor($this->criteria->section);
    }

    public function exportRows(int $limit = 2000): array
    {
        $this->guard();
        $page = $this->criteria->page;
        $perPage = $this->criteria->perPage;
        $this->criteria->page = 1;
        $this->criteria->perPage = min($limit, 100);
        $first = $this->tableFor($this->criteria->section);
        $rows = $first['items'];
        $total = (int) ($first['meta']['total'] ?? count($rows));
        $pages = min((int) ceil(max($total, 1) / $this->criteria->perPage), (int) ceil($limit / $this->criteria->perPage));

        for ($next = 2; $next <= $pages; $next++) {
            $this->criteria->page = $next;
            $rows = array_merge($rows, $this->tableFor($this->criteria->section)['items']);
        }

        $this->criteria->page = $page;
        $this->criteria->perPage = $perPage;

        return [
            'title' => $first['title'] ?? 'Records',
            'headers' => $first['headers'] ?? [],
            'items' => array_slice($rows, 0, $limit),
            'total' => $total,
        ];
    }

    private function guard(): void
    {
        abort_unless(ReportCatalog::allows($this->user, $this->criteria->section), 403, 'This action is unauthorized.');
    }

    private function frame(): array
    {
        $definition = ReportCatalog::definition($this->criteria->section);

        return [
            'section' => $this->criteria->section,
            'title' => $definition['title'] ?? 'Report',
            'hospital' => $this->query->hospital(),
            'generated_at' => now()->toIso8601String(),
            'range' => $this->criteria->range(),
            'applied' => $this->criteria->applied(),
            'tabs' => ReportCatalog::tabs($this->user),
            'schema' => ReportCatalog::schema($this->criteria->section),
        ];
    }

    private function section(): array
    {
        return match ($this->criteria->section) {
            'overview' => $this->overview(),
            'patients' => $this->patients(),
            'encounters' => $this->encounters(),
            'opd' => $this->opd(),
            'emergency' => $this->emergency(),
            'wards' => $this->wards(),
            'beds' => $this->beds(),
            'laboratory' => $this->orders('laboratory', 'Laboratory', 'flask', 'laboratory'),
            'imaging' => $this->orders('imaging', 'Imaging', 'scan', 'imaging'),
            'pharmacy' => $this->pharmacy(),
            'theatre' => $this->theatre(),
            'referrals' => $this->referrals(),
            'assistance' => $this->assistance(),
            'ambulances' => $this->ambulances(),
            'billing' => $this->billing(),
            'staff' => $this->staff(),
            default => abort(404, 'Unknown report.'),
        };
    }

    private function tableFor(string $section): array
    {
        if ($section === 'overview' && $this->user->isPlatformAdmin() && ! $this->user->hospital_id) {
            return $this->emptyTable('Network activity', 'No hospital-scoped activity for the network desk');
        }

        return match ($section) {
            'overview' => $this->canRead(['Opd', 'Emergency', 'Ward', 'Reception', 'Theatre'])
                ? $this->tableEncounters($this->query->encounterScope(), 'Recent encounters')
                : $this->tablePatients($this->query->patients(), 'Recent registrations'),
            'patients' => $this->tablePatients($this->query->patients(), 'Registrations'),
            'encounters' => $this->tableEncounters($this->query->encounterScope(), 'Clinical activity'),
            'opd' => $this->tableEncounters($this->query->encounterScope(types: $this->criteria->kind ? [$this->criteria->kind] : ['opd', 'follow_up']), 'OPD visits'),
            'emergency' => $this->tableEncounters($this->query->encounterScope('emergency'), 'Emergency visits'),
            'wards' => $this->tableEncounters($this->query->encounterScope('admission'), 'Inpatient encounters'),
            'beds' => $this->beds()['table'],
            'laboratory' => $this->tableOrders($this->query->orders('laboratory'), 'Laboratory orders'),
            'imaging' => $this->tableOrders($this->query->orders('imaging'), 'Imaging orders'),
            'pharmacy' => $this->pharmacy()['table'],
            'theatre' => $this->tableOrders($this->query->orders('theatre'), 'Theatre orders'),
            'referrals' => $this->referrals()['table'],
            'assistance' => $this->assistance()['table'],
            'ambulances' => $this->ambulances()['table'],
            'billing' => $this->billing()['table'],
            'staff' => $this->staff()['table'],
            default => $this->emptyTable('Records', 'No records'),
        };
    }

    private function overview(): array
    {
        if ($this->user->isPlatformAdmin() && ! $this->user->hospital_id) {
            $hospitals = Hospital::query()->where('is_active', true)->count();
            $users = User::query()->count();

            return [
                'kpis' => [
                    $this->kpi('hospitals', 'Hospitals', $hospitals, 'Active network sites', 'hospital', 'admin-hospitals'),
                    $this->kpi('users', 'Directory', $users, 'Accounts across the network', 'users', 'admin-users'),
                ],
                'charts' => [],
                'comparisons' => [],
                'exceptions' => [],
                'activity' => [],
                'table' => $this->emptyTable('Network activity', 'No hospital-scoped activity for the network desk'),
            ];
        }

        $encounters = $this->query->encounterScope();
        $previous = $this->query->encounterScope(previous: true);
        $current = (clone $encounters)->count();
        $prior = (clone $previous)->count();
        $patients = $this->query->patients();
        $registered = (clone $patients)->count();
        $open = (clone $encounters)->whereIn('status', ['waiting', 'in_progress'])->count();
        $beds = $this->query->beds();
        $capacity = (int) (clone $beds)->sum('capacity');
        $used = (int) (clone $beds)->sum('current_utilization');
        $occupancy = $this->query->percent($used, $capacity);
        $incoming = $this->user->hospital_id
            ? Referral::query()->where('to_hospital_id', $this->user->hospital_id)->where('status', 'pending')->count()
            : 0;
        $assistance = AssistanceRequest::query()->where('status', 'pending')->count();
        $issued = Invoice::query()->where('status', 'issued')->sum('total');

        return [
            'kpis' => [
                $this->kpi('encounters', 'Encounters', $current, 'Visits in the selected range', 'stethoscope', 'encounters', $open >= 20 ? 'warn' : null),
                $this->kpi('patients', 'New patients', $registered, 'Registrations in range', 'users', 'patients'),
                $this->kpi('occupancy', 'Bed occupancy', $occupancy.'%', $used.' of '.$capacity.' beds in use', 'bed', 'beds', $occupancy >= 90 ? 'danger' : ($occupancy >= 80 ? 'warn' : null)),
                $this->kpi('open', 'Open encounters', $open, 'Waiting or in progress now', 'desk', 'encounters', $open >= 20 ? 'warn' : null),
            ],
            'charts' => [
                $this->chart('trend', 'Daily encounters', 'trend', $this->query->series($encounters, 'created_at')),
                $this->chart('types', 'Encounter mix', 'bars', $this->query->barsFromCounts($this->query->counts($encounters, 'type'))),
            ],
            'comparisons' => [
                $this->compare('Encounters', $current, $prior),
                $this->compare('Registrations', $registered, (clone $this->query->patients(true))->count()),
                $this->compare('Invoiced', (int) Invoice::query()->whereBetween('created_at', [$this->criteria->from, $this->criteria->to])->sum('total'), (int) Invoice::query()->whereBetween('created_at', [$this->criteria->previousFrom(), $this->criteria->previousTo()])->sum('total')),
            ],
            'exceptions' => array_values(array_filter([
                $incoming ? $this->exception('Pending incoming referrals', $incoming, 'warn', 'referrals') : null,
                $assistance ? $this->exception('Open assistance requests', $assistance, 'warn', 'assistance') : null,
                $occupancy >= 80 ? $this->exception('High bed occupancy', $occupancy.'%', $occupancy >= 90 ? 'danger' : 'warn', 'beds') : null,
                (int) $issued ? $this->exception('Issued invoices outstanding', (int) $issued, 'warn', 'billing') : null,
            ])),
            'activity' => $this->recentEncounterActivity(),
            'table' => $this->canRead(['Opd', 'Emergency', 'Ward', 'Reception', 'Theatre'])
                ? $this->tableEncounters($encounters, 'Recent encounters')
                : $this->tablePatients($patients, 'Recent registrations'),
        ];
    }

    private function patients(): array
    {
        $query = $this->query->patients();
        $previous = $this->query->patients(true);
        $current = (clone $query)->count();
        $prior = (clone $previous)->count();
        $roster = $this->query->roster();
        $status = $this->query->counts($query, 'status');
        $sex = $this->query->counts($query, 'sex');
        $admitted = (clone $roster)->where('status', 'admitted')->count();
        $active = (clone $roster)->whereNotIn('status', ['discharged', 'deceased'])->count();

        return [
            'kpis' => [
                $this->kpi('registered', 'Registered', $current, 'New records in range', 'users', 'patients'),
                $this->kpi('active', 'Active roster', $active, 'Currently under care', 'community', 'patients'),
                $this->kpi('admitted', 'Admitted now', $admitted, 'Inpatient census', 'bed', 'wards'),
                $this->kpi('deceased', 'Deceased', (int) ($status['deceased'] ?? 0), 'Recorded in range', 'users'),
            ],
            'charts' => [
                $this->chart('trend', 'Registrations', 'trend', $this->query->series($query, 'created_at')),
                $this->chart('status', 'Registration status', 'bars', $this->query->barsFromCounts($status)),
                $this->chart('sex', 'Sex', 'bars', $this->query->barsFromCounts($sex)),
                $this->chart('age', 'Age bands', 'bars', $this->query->ageBands($query)),
            ],
            'comparisons' => [
                $this->compare('Registrations', $current, $prior),
                $this->compare('Admitted roster', $admitted, $admitted),
            ],
            'exceptions' => array_values(array_filter([
                ($status['transferred'] ?? 0) ? $this->exception('Transferred in range', (int) $status['transferred'], 'info', 'referrals') : null,
                $admitted ? $this->exception('Patients currently admitted', $admitted, 'warn', 'wards') : null,
            ])),
            'activity' => [],
            'table' => $this->tablePatients($query, 'Registrations'),
        ];
    }

    private function encounters(): array
    {
        return $this->clinical(
            $this->query->encounterScope(),
            $this->query->encounterScope(previous: true),
            'Clinical activity',
            Encounter::TYPES
        );
    }

    private function opd(): array
    {
        $types = $this->criteria->kind ? [$this->criteria->kind] : ['opd', 'follow_up'];

        return $this->clinical(
            $this->query->encounterScope(types: $types),
            $this->query->encounterScope(types: $types, previous: true),
            'OPD visits',
            $types,
            'opd'
        );
    }

    private function emergency(): array
    {
        $current = $this->query->encounterScope('emergency');
        $previous = $this->query->encounterScope('emergency', previous: true);
        $payload = $this->clinical($current, $previous, 'Emergency visits', ['emergency'], 'emergency');
        $admitted = Encounter::query()
            ->where('type', 'admission')
            ->whereIn('parent_encounter_id', (clone $current)->select('id'))
            ->count();
        $withTrip = (clone $current)->whereNotNull('ambulance_trip_id')->count();
        $payload['kpis'][] = $this->kpi('admitted', 'Admitted from board', $admitted, 'Linked inpatient encounters', 'hospital', 'wards');
        $payload['kpis'][] = $this->kpi('ambulance', 'Ambulance arrivals', $withTrip, 'Visits with a trip record', 'ambulance', 'ambulances');
        $waiting = (clone $current)->where('status', 'waiting')->count();
        if ($waiting) {
            $payload['exceptions'][] = $this->exception('Waiting now', $waiting, 'warn', 'emergency');
        }

        return $payload;
    }

    private function wards(): array
    {
        $current = $this->query->encounterScope('admission');
        $previous = $this->query->encounterScope('admission', previous: true);
        $admitted = (clone $current)->whereNotNull('admitted_at')->count();
        $discharged = Encounter::query()
            ->where('type', 'admission')
            ->whereNotNull('discharged_at');
        $this->query->applyDate($discharged, 'discharged_at');
        $this->query->applyDepartment($discharged);
        $this->query->applyFacility($discharged);
        $this->query->applyClinician($discharged);
        $stay = $this->query->averageInterval($discharged, 'admitted_at', 'discharged_at');
        $open = Encounter::query()->where('type', 'admission')->whereIn('status', ['waiting', 'in_progress']);
        $this->query->applyDepartment($open);
        $this->query->applyFacility($open);
        $this->query->applyClinician($open);
        $this->query->applyStatus($open);
        $census = (clone $open)->count();
        $assignments = $this->query->assignments();
        $payload = $this->clinical($current, $previous, 'Inpatient encounters', ['admission'], 'wards');
        $payload['kpis'] = [
            $this->kpi('admitted', 'Admitted', $admitted, 'Admissions started in range', 'hospital', 'wards'),
            $this->kpi('discharged', 'Discharged', (clone $discharged)->count(), 'Discharges in range', 'users', 'wards'),
            $this->kpi('census', 'Current inpatients', $census, 'Open admission encounters', 'bed', 'wards', $census >= 20 ? 'warn' : null),
            $this->kpi('alos', 'Average stay', $stay !== null ? $stay.'d' : '—', 'Completed admissions in range', 'clock'),
        ];
        $payload['charts'][] = $this->chart('assignments', 'Bed assignments', 'trend', $this->query->series($assignments, 'assigned_at'));
        $payload['exceptions'] = array_values(array_filter([
            $census ? $this->exception('Open admissions', $census, 'info', 'wards') : null,
            (clone $open)->where('status', 'waiting')->count() ? $this->exception('Awaiting bed', (clone $open)->where('status', 'waiting')->count(), 'warn', 'beds') : null,
        ]));
        $payload['table'] = $this->tableEncounters($current, 'Inpatient encounters');

        return $payload;
    }

    private function beds(): array
    {
        $beds = $this->query->beds();
        $wards = $this->query->wards();
        $capacity = (int) (clone $beds)->sum('capacity');
        $used = (int) (clone $beds)->sum('current_utilization');
        $available = (clone $beds)->where('status', 'available')->count();
        $occupied = (clone $beds)->where('status', 'occupied')->count();
        $blocked = (clone $beds)->whereIn('status', ['maintenance', 'unavailable', 'cleaning'])->count();
        $assignments = $this->query->assignments();
        $active = BedAssignment::query()->where('status', 'active');
        $this->query->applyFacility($active);
        if ($this->criteria->departmentId) {
            $active->whereIn('ward_id', Facility::query()->select('id')->where('department_id', $this->criteria->departmentId));
        }

        $wardRows = (clone $wards)
            ->orderBy('name')
            ->get(['id', 'name', 'capacity', 'current_utilization', 'status']);

        return [
            'kpis' => [
                $this->kpi('occupancy', 'Occupancy', $this->query->percent($used, $capacity).'%', $used.' of '.$capacity.' bed units', 'bed', 'beds', $this->query->percent($used, $capacity) >= 90 ? 'danger' : ($this->query->percent($used, $capacity) >= 80 ? 'warn' : null)),
                $this->kpi('available', 'Available', $available, 'Beds ready for assignment', 'bed', 'beds'),
                $this->kpi('occupied', 'Occupied', $occupied, 'Beds currently in use', 'hospital', 'beds'),
                $this->kpi('blocked', 'Unavailable', $blocked, 'Maintenance, cleaning, or blocked', 'cut', 'beds', $blocked ? 'warn' : null),
            ],
            'charts' => [
                $this->chart('status', 'Bed status', 'bars', $this->query->barsFromCounts($this->query->counts($beds, 'status'))),
                $this->chart('wards', 'Ward utilization', 'bars', $wardRows->map(fn (Facility $ward) => [
                    'label' => $ward->name,
                    'value' => (int) $ward->current_utilization,
                    'max' => max((int) $ward->capacity, (int) $ward->current_utilization, 1),
                ])->take(8)->values()->all()),
                $this->chart('assignments', 'Assignments', 'trend', $this->query->series($assignments, 'assigned_at')),
            ],
            'comparisons' => [
                $this->compare('Assignments', (clone $assignments)->count(), (clone $this->query->assignments(true))->count()),
                $this->compare('Active assignments', (clone $active)->count(), (clone $active)->count()),
            ],
            'exceptions' => array_values(array_filter([
                $blocked ? $this->exception('Beds out of service', $blocked, 'warn', 'beds') : null,
                $available === 0 && $capacity > 0 ? $this->exception('No available beds', 0, 'danger', 'beds') : null,
            ])),
            'activity' => $wardRows->take(8)->map(fn (Facility $ward) => [
                'title' => $ward->name,
                'meta' => $ward->current_utilization.' / '.$ward->capacity,
                'status' => $ward->status,
                'to' => ['name' => 'wards-id', 'params' => ['id' => $ward->id]],
            ])->all(),
            'table' => $this->paginate(
                (clone $beds)->with('type:id,name,slug')->with('parent:id,name')->orderBy('name'),
                fn (Facility $bed) => [
                    'id' => $bed->id,
                    'name' => $bed->name,
                    'code' => $bed->code,
                    'ward' => $bed->parent?->name,
                    'status' => $bed->status,
                    'capacity' => (int) $bed->capacity,
                    'utilization' => (int) $bed->current_utilization,
                    'remaining' => max(0, (int) $bed->capacity - (int) $bed->current_utilization),
                    'to' => ['name' => 'beds-id', 'params' => ['id' => $bed->id]],
                ],
                [
                    ['title' => 'Bed', 'key' => 'name'],
                    ['title' => 'Code', 'key' => 'code'],
                    ['title' => 'Ward', 'key' => 'ward'],
                    ['title' => 'Status', 'key' => 'status'],
                    ['title' => 'In use', 'key' => 'utilization'],
                    ['title' => 'Remaining', 'key' => 'remaining'],
                ],
                'Bed units',
                'name',
                'No beds match the current filters'
            ),
        ];
    }

    private function orders(string $module, string $title, string $icon, string $route): array
    {
        $query = $this->query->orders($module);
        $previous = $this->query->orders($module, true);
        $current = (clone $query)->count();
        $prior = (clone $previous)->count();
        $status = $this->query->counts($query, 'status');
        $open = (clone $query)->whereIn('status', ['requested', 'collected', 'scheduled', 'processing'])->count();
        $completed = (clone $query)->where('status', 'completed');
        $tat = $this->query->averageInterval($completed, 'requested_at', 'completed_at', 'hours');

        return [
            'kpis' => [
                $this->kpi('orders', $title.' orders', $current, 'Requested in range', $icon, $route),
                $this->kpi('open', 'Open queue', $open, 'Not yet completed', $icon, $route, $open >= 15 ? 'warn' : null),
                $this->kpi('completed', 'Completed', (int) ($status['completed'] ?? 0), 'Closed in this filtered set', 'check', $route),
                $this->kpi('tat', 'Average TAT', $tat !== null ? $tat.'h' : '—', 'Request to completion', 'clock'),
            ],
            'charts' => [
                $this->chart('trend', $title.' volume', 'trend', $this->query->series($query, 'requested_at')),
                $this->chart('status', 'Status mix', 'bars', $this->query->barsFromCounts($status)),
                $this->chart('facilities', 'By unit', 'bars', $this->query->namedBars($this->query->counts($query, 'facility_id'), Facility::class)),
            ],
            'comparisons' => [
                $this->compare('Orders', $current, $prior),
                $this->compare('Completed', (int) ($status['completed'] ?? 0), (clone $previous)->where('status', 'completed')->count()),
            ],
            'exceptions' => array_values(array_filter([
                ($status['requested'] ?? 0) ? $this->exception('Still requested', (int) $status['requested'], 'warn', $route) : null,
                ($status['processing'] ?? 0) ? $this->exception('In processing', (int) $status['processing'], 'info', $route) : null,
            ])),
            'activity' => [],
            'table' => $this->tableOrders($query, $title.' orders'),
        ];
    }

    private function pharmacy(): array
    {
        $query = $this->query->prescriptions();
        $previous = $this->query->prescriptions(true);
        $current = (clone $query)->count();
        $status = $this->query->counts($query, 'status');
        $open = (clone $query)->whereIn('status', ['pending', 'verified'])->count();
        $low = Medication::query()->whereColumn('stock_qty', '<=', 'reorder_level')->where('reorder_level', '>', 0);

        return [
            'kpis' => [
                $this->kpi('rx', 'Prescriptions', $current, 'Prescribed in range', 'pill', 'pharmacy'),
                $this->kpi('queue', 'Open queue', $open, 'Pending or verified', 'pill', 'pharmacy', $open >= 15 ? 'warn' : null),
                $this->kpi('dispensed', 'Dispensed', (int) ($status['dispensed'] ?? 0), 'Closed prescriptions', 'check', 'pharmacy'),
                $this->kpi('stock', 'Low stock', (clone $low)->count(), 'At or below reorder level', 'pill', 'pharmacy', (clone $low)->count() ? 'warn' : null),
            ],
            'charts' => [
                $this->chart('trend', 'Prescriptions', 'trend', $this->query->series($query, 'prescribed_at')),
                $this->chart('status', 'Status mix', 'bars', $this->query->barsFromCounts($status)),
            ],
            'comparisons' => [
                $this->compare('Prescriptions', $current, (clone $previous)->count()),
                $this->compare('Dispensed', (int) ($status['dispensed'] ?? 0), (clone $previous)->where('status', 'dispensed')->count()),
            ],
            'exceptions' => array_values(array_filter([
                $open ? $this->exception('Waiting on pharmacy', $open, 'warn', 'pharmacy') : null,
                (clone $low)->count() ? $this->exception('Medications at reorder', (clone $low)->count(), 'danger', 'pharmacy') : null,
            ])),
            'activity' => (clone $low)->orderBy('stock_qty')->limit(8)->get(['id', 'name', 'strength', 'form', 'stock_qty', 'reorder_level'])->map(fn (Medication $item) => [
                'title' => $item->label(),
                'meta' => $item->stock_qty.' on hand · reorder '.$item->reorder_level,
                'status' => 'low',
                'to' => ['name' => 'pharmacy'],
            ])->all(),
            'table' => $this->paginate(
                (clone $query)->with(['patient:id,mrn,first_name,last_name,status'])->latest('prescribed_at'),
                fn (Prescription $row) => [
                    'id' => $row->id,
                    'patient' => $this->query->patientName($row->patient),
                    'mrn' => $row->patient?->mrn,
                    'status' => $row->status,
                    'when' => optional($row->prescribed_at)->toIso8601String(),
                    'to' => $row->patient_id ? ['name' => 'patients-id', 'params' => ['id' => $row->patient_id]] : null,
                ],
                [
                    ['title' => 'Patient', 'key' => 'patient'],
                    ['title' => 'MRN', 'key' => 'mrn'],
                    ['title' => 'Status', 'key' => 'status'],
                    ['title' => 'Prescribed', 'key' => 'when'],
                ],
                'Prescriptions',
                'patient',
                'No prescriptions in this range'
            ),
        ];
    }

    private function theatre(): array
    {
        $orders = $this->orders('theatre', 'Theatre', 'cut', 'theatre');
        $cases = $this->query->encounterScope('procedure');
        $rooms = $this->query->theatres();
        $capacity = (int) (clone $rooms)->sum('capacity');
        $used = (int) (clone $rooms)->sum('current_utilization');
        $orders['kpis'][] = $this->kpi('cases', 'Procedure encounters', (clone $cases)->count(), 'Theatre cases in range', 'cut', 'theatre');
        $orders['kpis'][] = $this->kpi('rooms', 'Theatre use', $this->query->percent($used, $capacity).'%', $used.' of '.$capacity.' listed capacity', 'building', 'theatre');
        $orders['charts'][] = $this->chart('rooms', 'Theatre units', 'bars', (clone $rooms)->orderBy('name')->get(['name', 'capacity', 'current_utilization'])->map(fn (Facility $room) => [
            'label' => $room->name,
            'value' => (int) $room->current_utilization,
            'max' => max((int) $room->capacity, (int) $room->current_utilization, 1),
        ])->all());

        return $orders;
    }

    private function referrals(): array
    {
        $query = $this->query->referrals();
        $previous = $this->query->referrals(true);
        $current = (clone $query)->count();
        $status = $this->query->counts($query, 'status');
        $outgoing = $this->user->hospital_id ? (clone $query)->where('from_hospital_id', $this->user->hospital_id)->count() : $current;
        $incoming = $this->user->hospital_id ? (clone $query)->where('to_hospital_id', $this->user->hospital_id)->count() : 0;
        $pendingIn = $this->user->hospital_id
            ? Referral::query()->where('to_hospital_id', $this->user->hospital_id)->where('status', 'pending')->count()
            : (int) ($status['pending'] ?? 0);

        return [
            'kpis' => [
                $this->kpi('total', 'Referrals', $current, 'Created in range', 'transfer', 'referrals'),
                $this->kpi('outgoing', 'Outgoing', $outgoing, 'Originating here', 'transfer', 'referrals'),
                $this->kpi('incoming', 'Incoming', $incoming, 'Addressed to this hospital', 'hospital', 'referrals'),
                $this->kpi('pending', 'Pending incoming', $pendingIn, 'Awaiting a decision', 'clock', 'referrals', $pendingIn ? 'warn' : null),
            ],
            'charts' => [
                $this->chart('trend', 'Referral volume', 'trend', $this->query->series($query, 'created_at')),
                $this->chart('status', 'Status mix', 'bars', $this->query->barsFromCounts($status)),
                $this->chart('destinations', 'Destinations', 'bars', $this->query->namedBars($this->query->counts($query, 'to_hospital_id'), Hospital::class)),
            ],
            'comparisons' => [
                $this->compare('Referrals', $current, (clone $previous)->count()),
                $this->compare('Accepted', (int) ($status['accepted'] ?? 0), (clone $previous)->where('status', 'accepted')->count()),
            ],
            'exceptions' => array_values(array_filter([
                $pendingIn ? $this->exception('Awaiting acceptance', $pendingIn, 'warn', 'referrals') : null,
                ($status['more_info'] ?? 0) ? $this->exception('More information requested', (int) $status['more_info'], 'info', 'referrals') : null,
                ($status['in_transit'] ?? 0) ? $this->exception('In transit', (int) $status['in_transit'], 'info', 'ambulances') : null,
            ])),
            'activity' => [],
            'table' => $this->paginate(
                (clone $query)->with(['fromHospital:id,name,code', 'toHospital:id,name,code'])->latest(),
                fn (Referral $row) => [
                    'id' => $row->id,
                    'reference' => $row->patient_reference,
                    'from' => $row->fromHospital?->code,
                    'destination' => $row->toHospital?->code,
                    'status' => $row->status,
                    'when' => optional($row->created_at)->toIso8601String(),
                    'to' => ['name' => 'referrals-id', 'params' => ['id' => $row->id]],
                ],
                [
                    ['title' => 'Reference', 'key' => 'reference'],
                    ['title' => 'From', 'key' => 'from'],
                    ['title' => 'Destination', 'key' => 'destination'],
                    ['title' => 'Status', 'key' => 'status'],
                    ['title' => 'Opened', 'key' => 'when'],
                ],
                'Referrals',
                'reference',
                'No referrals in this range'
            ),
        ];
    }

    private function assistance(): array
    {
        $query = $this->query->assistance();
        $previous = $this->query->assistance(true);
        $current = (clone $query)->count();
        $status = $this->query->counts($query, 'status');
        $types = $this->query->counts($query, 'type');
        $pending = (int) ($status['pending'] ?? 0);

        return [
            'kpis' => [
                $this->kpi('total', 'Requests', $current, 'Opened in range', 'community', 'assistance'),
                $this->kpi('pending', 'Pending', $pending, 'Awaiting a response', 'clock', 'assistance', $pending ? 'warn' : null),
                $this->kpi('accepted', 'Accepted', (int) ($status['accepted'] ?? 0), 'Agreed in this set', 'check', 'assistance'),
                $this->kpi('fulfilled', 'Fulfilled', (int) ($status['fulfilled'] ?? 0), 'Closed successfully', 'check', 'assistance'),
            ],
            'charts' => [
                $this->chart('trend', 'Requests', 'trend', $this->query->series($query, 'created_at')),
                $this->chart('status', 'Status mix', 'bars', $this->query->barsFromCounts($status)),
                $this->chart('types', 'Request types', 'bars', $this->query->barsFromCounts($types)),
            ],
            'comparisons' => [
                $this->compare('Requests', $current, (clone $previous)->count()),
                $this->compare('Fulfilled', (int) ($status['fulfilled'] ?? 0), (clone $previous)->where('status', 'fulfilled')->count()),
            ],
            'exceptions' => array_values(array_filter([
                $pending ? $this->exception('Open assistance', $pending, 'warn', 'assistance') : null,
                ($status['declined'] ?? 0) ? $this->exception('Declined', (int) $status['declined'], 'danger', 'assistance') : null,
            ])),
            'activity' => [],
            'table' => $this->paginate(
                (clone $query)->with(['fromHospital:id,name,code', 'toHospital:id,name,code'])->latest(),
                fn (AssistanceRequest $row) => [
                    'id' => $row->id,
                    'title' => $row->title,
                    'type' => $row->type,
                    'from' => $row->fromHospital?->code,
                    'destination' => $row->toHospital?->code,
                    'status' => $row->status,
                    'quantity' => (int) $row->quantity,
                    'when' => optional($row->created_at)->toIso8601String(),
                    'to' => ['name' => 'assistance-id', 'params' => ['id' => $row->id]],
                ],
                [
                    ['title' => 'Request', 'key' => 'title'],
                    ['title' => 'Type', 'key' => 'type'],
                    ['title' => 'From', 'key' => 'from'],
                    ['title' => 'Destination', 'key' => 'destination'],
                    ['title' => 'Status', 'key' => 'status'],
                    ['title' => 'Qty', 'key' => 'quantity'],
                ],
                'Assistance requests',
                'title',
                'No assistance requests in this range'
            ),
        ];
    }

    private function ambulances(): array
    {
        $fleet = $this->query->ambulances();
        $trips = $this->query->trips();
        $previous = $this->query->trips(true);
        $fleetStatus = $this->query->counts($fleet, 'status');
        $tripStatus = $this->query->counts($trips, 'status');
        $completed = (clone $trips)->where('status', 'completed');
        $turnaround = $this->query->averageInterval($completed, 'dispatched_at', 'completed_at', 'hours');
        $active = (clone $trips)->whereIn('status', ['dispatched', 'en_route', 'arrived'])->count();

        return [
            'kpis' => [
                $this->kpi('ready', 'Ready', (int) ($fleetStatus['available'] ?? 0), 'Vehicles available to dispatch', 'ambulance', 'ambulances', ($fleetStatus['available'] ?? 0) === 0 ? 'warn' : null),
                $this->kpi('trips', 'Trips', (clone $trips)->count(), 'Dispatched in range', 'ambulance', 'ambulances'),
                $this->kpi('active', 'Active trips', $active, 'Currently on the road', 'clock', 'ambulances', $active ? 'info' : null),
                $this->kpi('cycle', 'Average trip', $turnaround !== null ? $turnaround.'h' : '—', 'Dispatch to completion', 'clock'),
            ],
            'charts' => [
                $this->chart('trend', 'Dispatches', 'trend', $this->query->series($trips, 'dispatched_at')),
                $this->chart('fleet', 'Fleet status', 'bars', $this->query->barsFromCounts($fleetStatus)),
                $this->chart('trips', 'Trip status', 'bars', $this->query->barsFromCounts($tripStatus)),
            ],
            'comparisons' => [
                $this->compare('Trips', (clone $trips)->count(), (clone $previous)->count()),
                $this->compare('Completed', (int) ($tripStatus['completed'] ?? 0), (clone $previous)->where('status', 'completed')->count()),
            ],
            'exceptions' => array_values(array_filter([
                ($fleetStatus['maintenance'] ?? 0) ? $this->exception('Vehicles in maintenance', (int) $fleetStatus['maintenance'], 'warn', 'ambulances') : null,
                $active ? $this->exception('Trips in progress', $active, 'info', 'ambulances') : null,
            ])),
            'activity' => (clone $fleet)->orderBy('vehicle_code')->limit(8)->get(['id', 'vehicle_code', 'vehicle_type', 'status'])->map(fn (Ambulance $row) => [
                'title' => $row->vehicle_code,
                'meta' => ReportCatalog::label($row->vehicle_type ?: 'vehicle'),
                'status' => $row->status,
                'to' => ['name' => 'ambulances-id', 'params' => ['id' => $row->id]],
            ])->all(),
            'table' => $this->paginate(
                (clone $trips)->with(['ambulance:id,vehicle_code,status', 'patient:id,mrn,first_name,last_name,status'])->latest('dispatched_at'),
                fn (AmbulanceTrip $row) => [
                    'id' => $row->id,
                    'vehicle' => $row->ambulance?->vehicle_code,
                    'patient' => $this->query->patientName($row->patient),
                    'status' => $row->status,
                    'origin' => $row->origin ?: $row->pickup_location,
                    'destination' => $row->destination,
                    'when' => optional($row->dispatched_at)->toIso8601String(),
                    'to' => $row->ambulance_id ? ['name' => 'ambulances-id', 'params' => ['id' => $row->ambulance_id]] : null,
                ],
                [
                    ['title' => 'Vehicle', 'key' => 'vehicle'],
                    ['title' => 'Patient', 'key' => 'patient'],
                    ['title' => 'Status', 'key' => 'status'],
                    ['title' => 'Origin', 'key' => 'origin'],
                    ['title' => 'Destination', 'key' => 'destination'],
                    ['title' => 'Dispatched', 'key' => 'when'],
                ],
                'Ambulance trips',
                'vehicle',
                'No trips in this range'
            ),
        ];
    }

    private function billing(): array
    {
        $query = $this->query->invoices();
        $previous = $this->query->invoices(true);
        $status = $this->query->counts($query, 'status');
        $billed = $this->query->sum($query, 'total');
        $paid = $this->query->sum((clone $query)->where('status', 'paid'), 'total');
        $outstanding = $this->query->sum((clone $query)->where('status', 'issued'), 'total');
        $payments = $this->query->payments();
        $collected = $this->query->sum($payments, 'amount');
        $methods = $this->query->counts($payments, 'method');

        return [
            'kpis' => [
                $this->kpi('billed', 'Billed', $billed, 'Invoice totals in range', 'receipt', 'billing'),
                $this->kpi('collected', 'Collected', $collected, 'Payments received in range', 'receipt', 'billing'),
                $this->kpi('outstanding', 'Outstanding', $outstanding, 'Issued and unpaid in this set', 'clock', 'billing', $outstanding ? 'warn' : null),
                $this->kpi('drafts', 'Drafts', (int) ($status['draft'] ?? 0), 'Not yet issued', 'edit', 'billing'),
            ],
            'charts' => [
                $this->chart('trend', 'Invoices', 'trend', $this->query->series($query, 'created_at')),
                $this->chart('status', 'Invoice status', 'bars', $this->query->barsFromCounts($status)),
                $this->chart('methods', 'Payment methods', 'bars', $this->query->barsFromCounts($methods)),
            ],
            'comparisons' => [
                $this->compare('Billed', $billed, $this->query->sum($previous, 'total')),
                $this->compare('Collected', $collected, $this->query->sum($this->query->payments(true), 'amount')),
            ],
            'exceptions' => array_values(array_filter([
                ($status['draft'] ?? 0) ? $this->exception('Draft invoices', (int) $status['draft'], 'info', 'billing') : null,
                $outstanding ? $this->exception('Issued outstanding', $outstanding, 'warn', 'billing') : null,
                ($status['cancelled'] ?? 0) ? $this->exception('Cancelled invoices', (int) $status['cancelled'], 'danger', 'billing') : null,
            ])),
            'activity' => [],
            'table' => $this->paginate(
                (clone $query)->with(['patient:id,mrn,first_name,last_name,status'])->latest(),
                fn (Invoice $row) => [
                    'id' => $row->id,
                    'number' => $row->number,
                    'patient' => $this->query->patientName($row->patient),
                    'mrn' => $row->patient?->mrn,
                    'status' => $row->status,
                    'total' => (int) $row->total,
                    'when' => optional($row->created_at)->toIso8601String(),
                    'to' => ['name' => 'billing-id', 'params' => ['id' => $row->id]],
                ],
                [
                    ['title' => 'Invoice', 'key' => 'number'],
                    ['title' => 'Patient', 'key' => 'patient'],
                    ['title' => 'MRN', 'key' => 'mrn'],
                    ['title' => 'Status', 'key' => 'status'],
                    ['title' => 'Total', 'key' => 'total'],
                    ['title' => 'Created', 'key' => 'when'],
                ],
                'Invoices',
                'number',
                'No invoices in this range'
            ),
        ];
    }

    private function staff(): array
    {
        $users = $this->query->staffUsers();
        $assignments = $this->query->staffAssignments();
        $encounters = $this->query->encounterScope();
        $roleBars = $this->query->namedBars($this->query->counts($users, 'role_id'), Role::class);

        $workload = $this->query->namedBars($this->query->counts($encounters, 'clinician_id'), User::class);
        $departments = $this->query->namedBars($this->query->counts($encounters, 'department_id'), Department::class);

        return [
            'kpis' => [
                $this->kpi('staff', 'Staff', (clone $users)->count(), 'Accounts in this hospital', 'users', 'admin-users'),
                $this->kpi('assignments', 'Assignments', (clone $assignments)->count(), 'Active duty records', 'shield', 'admin-departments'),
                $this->kpi('encounters', 'Attributed visits', (clone $encounters)->whereNotNull('clinician_id')->count(), 'Clinician-linked in range', 'stethoscope'),
                $this->kpi('departments', 'Departments', Department::query()->count(), 'Configured units', 'building', 'admin-departments'),
            ],
            'charts' => [
                $this->chart('roles', 'By role', 'bars', $roleBars),
                $this->chart('workload', 'Clinician volume', 'bars', $workload),
                $this->chart('departments', 'Department volume', 'bars', $departments),
                $this->chart('trend', 'Encounters', 'trend', $this->query->series($encounters, 'created_at')),
            ],
            'comparisons' => [
                $this->compare('Attributed visits', (clone $encounters)->whereNotNull('clinician_id')->count(), (clone $this->query->encounterScope(previous: true))->whereNotNull('clinician_id')->count()),
            ],
            'exceptions' => array_values(array_filter([
                (clone $assignments)->where('status', 'active')->count() === 0
                    ? $this->exception('No active staff assignments', 0, 'warn', 'admin-departments')
                    : null,
            ])),
            'activity' => StaffAssignment::query()
                ->with(['user:id,name', 'department:id,name', 'facility:id,name,code'])
                ->when($this->criteria->departmentId, fn ($query) => $query->where('department_id', $this->criteria->departmentId))
                ->when($this->criteria->facilityId, fn ($query) => $query->where('facility_id', $this->criteria->facilityId))
                ->latest('starts_at')
                ->limit(8)
                ->get()
                ->map(fn (StaffAssignment $row) => [
                    'title' => $row->user?->name ?: 'Staff',
                    'meta' => trim(($row->department?->name ?? '').' · '.($row->shift ?? '')),
                    'status' => $row->status,
                    'to' => $row->user_id ? ['name' => 'admin-users-id', 'params' => ['id' => $row->user_id]] : null,
                ])->all(),
            'table' => $this->paginate(
                (clone $users)->with(['role:id,name,slug', 'department:id,name'])->orderBy('name'),
                function (User $row) {
                    $visits = Encounter::query()
                        ->where('clinician_id', $row->id)
                        ->whereBetween('created_at', [$this->criteria->from, $this->criteria->to])
                        ->count();

                    return [
                        'id' => $row->id,
                        'name' => $row->name,
                        'role' => $row->role?->name,
                        'department' => $row->department?->name,
                        'status' => $row->availability ?: 'active',
                        'visits' => $visits,
                        'to' => ['name' => 'admin-users-id', 'params' => ['id' => $row->id]],
                    ];
                },
                [
                    ['title' => 'Name', 'key' => 'name'],
                    ['title' => 'Role', 'key' => 'role'],
                    ['title' => 'Department', 'key' => 'department'],
                    ['title' => 'Availability', 'key' => 'status'],
                    ['title' => 'Visits', 'key' => 'visits'],
                ],
                'Staff directory',
                'name',
                'No staff match the current filters'
            ),
        ];
    }

    private function clinical(Builder $current, Builder $previous, string $title, array $types, ?string $route = 'encounters'): array
    {
        $count = (clone $current)->count();
        $prior = (clone $previous)->count();
        $status = $this->query->counts($current, 'status');
        $open = (clone $current)->whereIn('status', ['waiting', 'in_progress'])->count();
        $completed = (int) ($status['completed'] ?? 0);
        $departments = $this->query->namedBars($this->query->counts($current, 'department_id'), Department::class);
        $clinicians = $this->query->namedBars($this->query->counts($current, 'clinician_id'), User::class);

        return [
            'kpis' => [
                $this->kpi('volume', $title, $count, 'Encounters in range', 'stethoscope', $route),
                $this->kpi('open', 'Open', $open, 'Waiting or in progress in this set', 'clock', $route, $open >= 15 ? 'warn' : null),
                $this->kpi('completed', 'Completed', $completed, $this->query->percent($completed, $count).'% of the filtered set', 'check', $route),
                $this->kpi('cancelled', 'Cancelled', (int) ($status['cancelled'] ?? 0), 'Cancelled in range', 'x', $route),
            ],
            'charts' => array_values(array_filter([
                $this->chart('trend', $title, 'trend', $this->query->series($current, 'created_at')),
                $this->chart('status', 'Status mix', 'bars', $this->query->barsFromCounts($status)),
                count($types) > 1 ? $this->chart('types', 'Types', 'bars', $this->query->barsFromCounts($this->query->counts($current, 'type'))) : null,
                $this->chart('departments', 'Departments', 'bars', $departments),
                $this->chart('clinicians', 'Clinicians', 'bars', $clinicians),
            ])),
            'comparisons' => [
                $this->compare($title, $count, $prior),
                $this->compare('Completed', $completed, (clone $previous)->where('status', 'completed')->count()),
            ],
            'exceptions' => array_values(array_filter([
                ($status['waiting'] ?? 0) ? $this->exception('Waiting', (int) $status['waiting'], 'warn', $route) : null,
                ($status['transferred'] ?? 0) ? $this->exception('Transferred', (int) $status['transferred'], 'info', 'referrals') : null,
            ])),
            'activity' => $this->recentEncounterActivity($current),
            'table' => $this->tableEncounters($current, $title),
        ];
    }

    private function tablePatients(Builder $query, string $title): array
    {
        return $this->paginate(
            (clone $query)->latest(),
            fn (Patient $row) => [
                'id' => $row->id,
                'mrn' => $row->mrn,
                'patient' => $this->query->patientName($row),
                'sex' => $row->sex,
                'status' => $row->status,
                'when' => optional($row->created_at)->toIso8601String(),
                'to' => ['name' => 'patients-id', 'params' => ['id' => $row->id]],
            ],
            [
                ['title' => 'MRN', 'key' => 'mrn'],
                ['title' => 'Patient', 'key' => 'patient'],
                ['title' => 'Sex', 'key' => 'sex'],
                ['title' => 'Status', 'key' => 'status'],
                ['title' => 'Registered', 'key' => 'when'],
            ],
            $title,
            'patient',
            'No patients in this range'
        );
    }

    private function tableEncounters(Builder $query, string $title): array
    {
        return $this->paginate(
            (clone $query)->with(['patient:id,mrn,first_name,last_name,status', 'department:id,name', 'clinician:id,name'])->latest(),
            fn (Encounter $row) => [
                'id' => $row->id,
                'patient' => $this->query->patientName($row->patient),
                'mrn' => $row->patient?->mrn,
                'type' => $row->type,
                'status' => $row->status,
                'department' => $row->department?->name,
                'clinician' => $row->clinician?->name,
                'when' => optional($row->created_at)->toIso8601String(),
                'to' => ['name' => 'encounters-id', 'params' => ['id' => $row->id]],
            ],
            [
                ['title' => 'Patient', 'key' => 'patient'],
                ['title' => 'MRN', 'key' => 'mrn'],
                ['title' => 'Type', 'key' => 'type'],
                ['title' => 'Status', 'key' => 'status'],
                ['title' => 'Department', 'key' => 'department'],
                ['title' => 'Clinician', 'key' => 'clinician'],
                ['title' => 'Opened', 'key' => 'when'],
            ],
            $title,
            'patient',
            'No encounters in this range'
        );
    }

    private function tableOrders(Builder $query, string $title): array
    {
        return $this->paginate(
            (clone $query)->with(['patient:id,mrn,first_name,last_name,status', 'facility:id,name,code'])->latest('requested_at'),
            fn (ServiceOrder $row) => [
                'id' => $row->id,
                'patient' => $this->query->patientName($row->patient),
                'mrn' => $row->patient?->mrn,
                'item' => $row->item_name,
                'status' => $row->status,
                'unit' => $row->facility?->name,
                'when' => optional($row->requested_at ?: $row->created_at)->toIso8601String(),
                'to' => $row->patient_id ? ['name' => 'patients-id', 'params' => ['id' => $row->patient_id]] : ($row->encounter_id ? ['name' => 'encounters-id', 'params' => ['id' => $row->encounter_id]] : null),
            ],
            [
                ['title' => 'Patient', 'key' => 'patient'],
                ['title' => 'MRN', 'key' => 'mrn'],
                ['title' => 'Item', 'key' => 'item'],
                ['title' => 'Status', 'key' => 'status'],
                ['title' => 'Unit', 'key' => 'unit'],
                ['title' => 'Requested', 'key' => 'when'],
            ],
            $title,
            'patient',
            'No orders in this range'
        );
    }

    private function recentEncounterActivity(?Builder $query = null): array
    {
        $source = $query ? (clone $query) : $this->query->encounterScope();

        return $source->with(['patient:id,mrn,first_name,last_name,status'])->latest()->limit(8)->get()->map(fn (Encounter $row) => [
            'title' => $this->query->patientName($row->patient),
            'meta' => trim(ReportCatalog::label($row->type).' · '.ReportCatalog::label($row->status)),
            'status' => $row->status,
            'to' => ['name' => 'encounters-id', 'params' => ['id' => $row->id]],
        ])->all();
    }

    private function paginate(Builder $query, callable $map, array $headers, string $title, string $linkKey, string $empty): array
    {
        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($this->criteria->perPage, ['*'], 'page', $this->criteria->page);
        $paginator->setCollection($paginator->getCollection()->map($map)->values());

        return [
            'title' => $title,
            'headers' => $headers,
            'items' => $paginator->items(),
            'link_key' => $linkKey,
            'empty' => $empty,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function emptyTable(string $title, string $empty): array
    {
        return [
            'title' => $title,
            'headers' => [['title' => 'Record', 'key' => 'title']],
            'items' => [],
            'link_key' => 'title',
            'empty' => $empty,
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 25, 'total' => 0],
        ];
    }

    private function kpi(string $key, string $title, int|string $value, string $hint, string $icon, ?string $to = null, ?string $tone = null): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'value' => $value,
            'hint' => $hint,
            'icon' => $icon,
            'to' => $to,
            'tone' => $tone,
        ];
    }

    private function chart(string $key, string $title, string $type, array $items): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'type' => $type,
            'items' => $items,
        ];
    }

    private function compare(string $label, int $current, int $previous): array
    {
        return [
            'label' => $label,
            'current' => $current,
            'previous' => $previous,
            'delta' => $this->query->change($current, $previous),
        ];
    }

    private function exception(string $title, int|string $value, string $tone, ?string $to = null): array
    {
        return [
            'title' => $title,
            'value' => $value,
            'tone' => $tone,
            'to' => $to,
        ];
    }

    private function canRead(array $subjects): bool
    {
        foreach ($subjects as $subject) {
            if ($this->user->hasPermission('read', $subject)) {
                return true;
            }
        }

        return false;
    }
}
