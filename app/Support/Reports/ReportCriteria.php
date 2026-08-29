<?php

namespace App\Support\Reports;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReportCriteria
{
    public function __construct(
        public string $section,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?string $departmentId,
        public ?string $facilityId,
        public ?string $clinicianId,
        public ?string $status,
        public ?string $patientType,
        public ?string $kind,
        public int $page,
        public int $perPage,
        public string $scope = 'section',
    ) {}

    public static function fromRequest(Request $request, string $defaultSection = 'overview'): self
    {
        $section = (string) $request->string('section', $defaultSection);
        if (! ReportCatalog::definition($section)) {
            $section = $defaultSection;
        }

        $to = self::date($request->input('to')) ?? CarbonImmutable::now()->endOfDay();
        $from = self::date($request->input('from')) ?? $to->subDays(29)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        if ($from->diffInDays($to) > 366) {
            $from = $to->subDays(366)->startOfDay();
        }

        return new self(
            section: $section,
            from: $from->startOfDay(),
            to: $to->endOfDay(),
            departmentId: self::id($request->input('department_id')),
            facilityId: self::id($request->input('facility_id')),
            clinicianId: self::id($request->input('clinician_id')),
            status: self::token($request->input('status')),
            patientType: self::token($request->input('patient_type')),
            kind: self::token($request->input('kind')),
            page: max(1, (int) $request->integer('page', 1)),
            perPage: min(100, max(1, (int) $request->integer('per_page', 25))),
            scope: strtolower((string) $request->string('scope', 'section')) === 'complete' ? 'complete' : 'section',
        );
    }

    public function forSection(string $section): self
    {
        $filters = ReportCatalog::schema($section)['filters'] ?? [];
        $same = $section === $this->section;

        return new self(
            section: $section,
            from: $this->from,
            to: $this->to,
            departmentId: in_array('department_id', $filters, true) ? $this->departmentId : null,
            facilityId: in_array('facility_id', $filters, true) ? $this->facilityId : null,
            clinicianId: in_array('clinician_id', $filters, true) ? $this->clinicianId : null,
            status: $same && in_array('status', $filters, true) ? $this->status : null,
            patientType: $same && in_array('patient_type', $filters, true) ? $this->patientType : null,
            kind: $same && in_array('kind', $filters, true) ? $this->kind : null,
            page: 1,
            perPage: $this->perPage,
            scope: 'section',
        );
    }

    public function moduleKeys(User $user): array
    {
        if ($this->scope !== 'complete') {
            return [$this->section];
        }

        return array_values(array_filter(
            array_keys(ReportCatalog::sections()),
            fn (string $key) => ReportCatalog::allows($user, $key)
        ));
    }

    public function previousFrom(): CarbonImmutable
    {
        $days = $this->from->diffInDays($this->to) + 1;

        return $this->from->subDays($days)->startOfDay();
    }

    public function previousTo(): CarbonImmutable
    {
        return $this->from->subSecond();
    }

    public function range(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
        ];
    }

    public function applied(): array
    {
        return array_filter([
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'department_id' => $this->departmentId,
            'facility_id' => $this->facilityId,
            'clinician_id' => $this->clinicianId,
            'status' => $this->status,
            'patient_type' => $this->patientType,
            'kind' => $this->kind,
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function query(): array
    {
        return $this->applied() + ['section' => $this->section];
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function id(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private static function token(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }
}
