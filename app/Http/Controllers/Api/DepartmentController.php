<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Support\HospitalProvisioner;
use App\Support\ModuleCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        return Department::query()->withCount('facilities')->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'module_key' => ['required', Rule::in(collect(ModuleCatalog::all())->pluck('key')->all())],
            'is_active' => ['boolean'],
        ]);

        $department = Department::query()->create([
            'hospital_id' => $user->hospital_id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::random(4),
            'module_key' => $data['module_key'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($department, 201);
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'module_key' => ['sometimes', Rule::in(collect(ModuleCatalog::all())->pluck('key')->all())],
            'is_active' => ['boolean'],
        ]);

        $department->update($data);

        return $department->refresh();
    }

    public function destroy(Department $department)
    {
        abort_if($department->facilities()->exists(), 422, 'Reassign facilities before deleting this department.');

        $department->delete();

        return response()->json(['message' => 'Department removed']);
    }

    public function restoreDefaults(Request $request)
    {
        abort_unless($request->user()->hospital_id, 422, 'Hospital context is required.');
        HospitalProvisioner::seedDepartments($request->user()->hospital);

        return Department::query()->orderBy('name')->get();
    }
}
