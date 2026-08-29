<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffAssignment;
use App\Support\TenantRules;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffAssignmentController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            $request->user()->hasPermission('read', 'User')
            || $request->user()->hasPermission('read', 'Department')
            || $request->user()->hasPermission('read', 'Ward'),
            403,
            'This action is unauthorized.'
        );

        $query = StaffAssignment::query()
            ->with(['user:id,name,email,job_title,department_id', 'department:id,name,slug', 'facility:id,name,code'])
            ->latest();

        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($facilityId = $request->input('facility_id')) {
            $query->where('facility_id', $facilityId);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return $query->limit(200)->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'department_id' => ['nullable', TenantRules::inHospital('departments')],
            'facility_id' => ['nullable', TenantRules::inHospital('facilities')],
            'assignment_role' => ['nullable', 'string', 'max:80'],
            'shift' => ['nullable', Rule::in(StaffAssignment::SHIFTS)],
            'status' => ['sometimes', 'string', 'max:40'],
        ]);

        $assignment = StaffAssignment::query()->create([
            ...$data,
            'hospital_id' => $request->user()->hospital_id,
            'status' => $data['status'] ?? 'active',
            'starts_at' => now(),
        ]);

        return response()->json($assignment->load(['user', 'department', 'facility']), 201);
    }

    public function update(Request $request, StaffAssignment $staffAssignment)
    {
        $data = $request->validate([
            'department_id' => ['nullable', TenantRules::inHospital('departments')],
            'facility_id' => ['nullable', TenantRules::inHospital('facilities')],
            'assignment_role' => ['nullable', 'string', 'max:80'],
            'shift' => ['nullable', Rule::in(StaffAssignment::SHIFTS)],
            'status' => ['sometimes', 'string', 'max:40'],
        ]);

        $staffAssignment->update($data);

        return $staffAssignment->refresh()->load(['user', 'department', 'facility']);
    }

    public function destroy(StaffAssignment $staffAssignment)
    {
        $staffAssignment->update([
            'status' => 'ended',
            'ends_at' => now(),
        ]);

        return response()->json(['message' => 'Assignment closed']);
    }
}
