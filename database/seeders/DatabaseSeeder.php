<?php

namespace Database\Seeders;

use App\Models\Ambulance;
use App\Models\AssistanceRequest;
use App\Models\Department;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Hospital;
use App\Models\HospitalMembership;
use App\Models\Role;
use App\Models\User;
use App\Support\HospitalProvisioner;
use App\Support\RoleProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        RoleProvisioner::seedPermissions();
        $platformRole = RoleProvisioner::seedPlatformAdmin();

        $riverside = Hospital::query()->create([
            'name' => 'Riverside General Hospital',
            'code' => 'RGH',
            'city' => 'Riverside',
            'region' => 'North',
            'phone' => '555-0100',
            'email' => 'ops@riverside.test',
            'address' => '100 River Road',
            'is_active' => true,
        ]);

        $lakeside = Hospital::query()->create([
            'name' => 'Lakeside Medical Center',
            'code' => 'LMC',
            'city' => 'Lakeside',
            'region' => 'South',
            'phone' => '555-0200',
            'email' => 'ops@lakeside.test',
            'address' => '22 Harbor Lane',
            'is_active' => true,
        ]);

        $riversideRoles = HospitalProvisioner::bootstrap($riverside);
        $lakesideRoles = HospitalProvisioner::bootstrap($lakeside);

        $password = Hash::make('password');

        $platform = User::query()->create([
            'name' => 'Platform Operator',
            'email' => 'platform@health.test',
            'password' => $password,
            'role_id' => $platformRole->id,
            'hospital_id' => null,
            'job_title' => 'Network Director',
        ]);

        $users = [
            ['Amina Okonkwo', 'admin@riverside.test', 'administrator', $riverside, $riversideRoles, 'Hospital Administrator'],
            ['Daniel Mensah', 'doctor@riverside.test', 'doctor', $riverside, $riversideRoles, 'Consultant'],
            ['Grace Adeyemi', 'nurse@riverside.test', 'nurse', $riverside, $riversideRoles, 'Charge Nurse'],
            ['Samuel Boateng', 'reception@riverside.test', 'reception', $riverside, $riversideRoles, 'Front Desk'],
            ['Kwesi Darko', 'ambulance@riverside.test', 'ambulance-team', $riverside, $riversideRoles, 'Dispatch Lead'],
            ['Lydia Kwarteng', 'manager@riverside.test', 'facility-manager', $riverside, $riversideRoles, 'Facilities Lead'],
            ['Ivy Owusu', 'lab@riverside.test', 'lab-staff', $riverside, $riversideRoles, 'Lab Technician'],
            ['Nora Asante', 'pharmacy@riverside.test', 'pharmacy-staff', $riverside, $riversideRoles, 'Pharmacist'],
            ['Akosua Mensah', 'imaging@riverside.test', 'imaging-staff', $riverside, $riversideRoles, 'Radiographer'],
            ['Yaw Oppong', 'theatre@riverside.test', 'theatre-staff', $riverside, $riversideRoles, 'Theatre Lead'],
            ['Efua Boateng', 'emergency@riverside.test', 'emergency-staff', $riverside, $riversideRoles, 'ER Officer'],
            ['Kofi Sarpong', 'billing@riverside.test', 'billing-clerk', $riverside, $riversideRoles, 'Billing Clerk'],
            ['Michael Addo', 'admin@lakeside.test', 'administrator', $lakeside, $lakesideRoles, 'Hospital Administrator'],
            ['Helena Frimpong', 'doctor@lakeside.test', 'doctor', $lakeside, $lakesideRoles, 'Specialist'],
        ];

        $userModels = ['platform@health.test' => $platform];

        foreach ($users as $entry) {
            $userModels[$entry[1]] = $this->createHospitalUser(
                $entry[0],
                $entry[1],
                $password,
                $entry[2],
                $entry[3],
                $entry[4],
                $entry[5]
            );
        }

        $locum = User::query()->create([
            'name' => 'Josephine Addai',
            'email' => 'locum@health.test',
            'password' => $password,
            'role_id' => $riversideRoles['doctor']->id,
            'hospital_id' => $riverside->id,
            'job_title' => 'Locum Clinician',
        ]);
        $this->assignMembership($locum, $riverside, $riversideRoles['doctor']);
        $this->assignMembership($locum, $lakeside, $lakesideRoles['nurse']);
        $userModels['locum@health.test'] = $locum;

        $types = [
            ['Laboratory', 'lab', 'tabler-test-pipe'],
            ['Ward', 'ward', 'tabler-bed'],
            ['Bed', 'bed', 'tabler-bed-flat'],
            ['Pharmacy', 'pharmacy', 'tabler-pill'],
            ['Theatre', 'theatre', 'tabler-cut'],
            ['Imaging', 'imaging', 'tabler-scan'],
            ['Consultation Room', 'consultation-room', 'tabler-stethoscope'],
            ['Emergency Unit', 'emergency-unit', 'tabler-emergency-bed'],
        ];

        $typeModels = [];
        foreach ($types as $type) {
            $typeModels[$type[1]] = FacilityType::query()->create([
                'name' => $type[0],
                'slug' => $type[1],
                'icon' => $type[2],
            ]);
        }

        $this->seedFacilities($riverside, $typeModels);
        $this->seedFacilities($lakeside, $typeModels, true);

        $ambulance = Ambulance::query()->create([
            'hospital_id' => $riverside->id,
            'vehicle_code' => 'AMB-01',
            'vehicle_type' => 'advanced-life-support',
            'status' => 'available',
            'capacity' => 2,
        ]);
        $ambulance->staff()->create([
            'user_id' => $userModels['ambulance@riverside.test']->id,
            'assignment_role' => 'driver',
        ]);

        Ambulance::query()->create([
            'hospital_id' => $lakeside->id,
            'vehicle_code' => 'AMB-10',
            'vehicle_type' => 'van',
            'status' => 'available',
            'capacity' => 2,
        ]);

        $this->call(ClinicalJourneySeeder::class);

        AssistanceRequest::query()->create([
            'from_hospital_id' => $riverside->id,
            'to_hospital_id' => $lakeside->id,
            'type' => 'staff',
            'title' => 'Request for theatre nurses',
            'description' => 'Need two theatre nurses for a 48-hour surge.',
            'status' => 'pending',
            'created_by' => $userModels['manager@riverside.test']->id,
        ]);

        Facility::withoutGlobalScope('hospital')
            ->whereHas('type', fn ($query) => $query->where('slug', 'ward'))
            ->get()
            ->each(fn (Facility $ward) => \App\Support\FacilityOccupancy::syncWard($ward));
    }

    private function createHospitalUser(string $name, string $email, string $password, string $roleSlug, Hospital $hospital, array $roles, string $jobTitle): User
    {
        $role = $roles[$roleSlug];
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role_id' => $role->id,
            'hospital_id' => $hospital->id,
            'job_title' => $jobTitle,
        ]);
        $this->assignMembership($user, $hospital, $role);

        return $user;
    }

    private function assignMembership(User $user, Hospital $hospital, Role $role): void
    {
        HospitalMembership::query()->updateOrCreate(
            ['user_id' => $user->id, 'hospital_id' => $hospital->id],
            ['role_id' => $role->id]
        );
    }

    private function department(Hospital $hospital, string $slug): ?Department
    {
        return Department::query()->where('hospital_id', $hospital->id)->where('slug', $slug)->first();
    }

    private function seedFacilities(Hospital $hospital, array $types, bool $withSpareIcu = false): void
    {
        $ward = Facility::withoutGlobalScope('hospital')->create([
            'hospital_id' => $hospital->id,
            'facility_type_id' => $types['ward']->id,
            'department_id' => $this->department($hospital, 'wards')?->id,
            'name' => 'General Ward A',
            'code' => 'WARD-A',
            'status' => 'available',
            'capacity' => 20,
            'current_utilization' => $withSpareIcu ? 8 : 14,
        ]);

        Facility::withoutGlobalScope('hospital')->create([
            'hospital_id' => $hospital->id,
            'facility_type_id' => $types['ward']->id,
            'department_id' => $this->department($hospital, 'wards')?->id,
            'name' => 'ICU 1',
            'code' => 'ICU-1',
            'status' => 'available',
            'capacity' => 6,
            'current_utilization' => $withSpareIcu ? 2 : 6,
            'parent_id' => $ward->id,
        ]);

        $occupiedBeds = $withSpareIcu ? 2 : 5;
        for ($i = 1; $i <= 8; $i++) {
            $used = $i <= $occupiedBeds ? 1 : 0;
            Facility::withoutGlobalScope('hospital')->create([
                'hospital_id' => $hospital->id,
                'facility_type_id' => $types['bed']->id,
                'department_id' => $this->department($hospital, 'wards')?->id,
                'parent_id' => $ward->id,
                'name' => 'Bed '.$i,
                'code' => 'BED-'.$i,
                'status' => $used ? 'occupied' : 'available',
                'capacity' => 1,
                'current_utilization' => $used,
            ]);
        }

        Facility::withoutGlobalScope('hospital')->create([
            'hospital_id' => $hospital->id,
            'facility_type_id' => $types['lab']->id,
            'department_id' => $this->department($hospital, 'laboratory')?->id,
            'name' => 'Central Laboratory',
            'code' => 'LAB-1',
            'status' => 'available',
            'capacity' => 30,
            'current_utilization' => 12,
        ]);

        Facility::withoutGlobalScope('hospital')->create([
            'hospital_id' => $hospital->id,
            'facility_type_id' => $types['pharmacy']->id,
            'department_id' => $this->department($hospital, 'pharmacy')?->id,
            'name' => 'Main Pharmacy',
            'code' => 'PHARM-1',
            'status' => 'available',
            'capacity' => 50,
            'current_utilization' => 20,
        ]);

        Facility::withoutGlobalScope('hospital')->create([
            'hospital_id' => $hospital->id,
            'facility_type_id' => $types['theatre']->id,
            'department_id' => $this->department($hospital, 'theatre')?->id,
            'name' => 'Theatre 1',
            'code' => 'TH-1',
            'status' => $withSpareIcu ? 'available' : 'occupied',
            'capacity' => 1,
            'current_utilization' => $withSpareIcu ? 0 : 1,
        ]);

        Facility::withoutGlobalScope('hospital')->create([
            'hospital_id' => $hospital->id,
            'facility_type_id' => $types['imaging']->id,
            'department_id' => $this->department($hospital, 'imaging')?->id,
            'name' => 'CT Suite',
            'code' => 'IMG-CT',
            'status' => 'available',
            'capacity' => 8,
            'current_utilization' => 3,
        ]);

        Facility::withoutGlobalScope('hospital')->create([
            'hospital_id' => $hospital->id,
            'facility_type_id' => $types['consultation-room']->id,
            'department_id' => $this->department($hospital, 'opd')?->id,
            'name' => 'Consult 3',
            'code' => 'CON-3',
            'status' => 'available',
            'capacity' => 1,
            'current_utilization' => 0,
        ]);

        Facility::withoutGlobalScope('hospital')->create([
            'hospital_id' => $hospital->id,
            'facility_type_id' => $types['emergency-unit']->id,
            'department_id' => $this->department($hospital, 'emergency')?->id,
            'name' => 'Emergency Bay',
            'code' => 'ER-1',
            'status' => 'available',
            'capacity' => 10,
            'current_utilization' => 4,
        ]);
    }
}
