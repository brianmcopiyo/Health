<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Models\User;
use App\Support\FieldCrypt;
use App\Support\HospitalSequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VolumeSeeder extends Seeder
{
    public int $patients = 1200;

    public function run(): void
    {
        $hospital = Hospital::query()->where('code', 'RGH')->firstOrFail();
        $doctor = User::query()->where('email', 'doctor@riverside.test')->firstOrFail();
        $now = now()->toDateTimeString();
        $count = max(50, $this->patients);
        $start = HospitalSequence::bump($hospital, 'patient_seq', $count);

        $patientRows = [];
        for ($i = 0; $i < $count; $i++) {
            $n = $start + $i;
            $phone = '555-'.str_pad((string) (2000 + $i), 4, '0', STR_PAD_LEFT);
            $patientRows[] = [
                'id' => (string) Str::uuid(),
                'hospital_id' => $hospital->id,
                'mrn' => sprintf('%s-%04d', $hospital->code, $n),
                'first_name' => $i % 40 === 0 ? 'Patience' : 'Kofi',
                'last_name' => $i % 40 === 0 ? 'Patton' : 'Boateng',
                'sex' => $i % 2 === 0 ? 'male' : 'female',
                'phone' => FieldCrypt::encrypt($phone),
                'phone_index' => FieldCrypt::blindIndex(FieldCrypt::normalizePhone($phone)),
                'phone_tail_index' => FieldCrypt::blindIndex(FieldCrypt::phoneTail(FieldCrypt::normalizePhone($phone))),
                'status' => $i % 17 === 0 ? 'admitted' : 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($patientRows, 200) as $chunk) {
            DB::table('patients')->insert($chunk);
        }

        $patientIds = DB::table('patients')
            ->where('hospital_id', $hospital->id)
            ->where('mrn', '>=', sprintf('%s-%04d', $hospital->code, $start))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $encounterRows = [];
        $orderRows = [];
        $invoiceRows = [];
        $invoiceStart = HospitalSequence::bump($hospital, 'invoice_seq', (int) floor($count / 2));
        $invoiceCursor = $invoiceStart;

        foreach ($patientIds as $index => $patientId) {
            $type = $index % 3 === 0 ? 'emergency' : 'opd';
            $status = $index % 5 === 0 ? 'waiting' : ($index % 5 === 1 ? 'in_progress' : 'completed');
            $encounterRows[] = [
                'id' => (string) Str::uuid(),
                'hospital_id' => $hospital->id,
                'patient_id' => $patientId,
                'clinician_id' => $doctor->id,
                'type' => $type,
                'status' => $status,
                'chief_complaint' => FieldCrypt::encrypt($type === 'emergency' ? 'Acute pain' : 'Follow-up review'),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($index % 2 === 0) {
                $orderRows[] = [
                    'id' => (string) Str::uuid(),
                    'hospital_id' => $hospital->id,
                    'patient_id' => $patientId,
                    'ordered_by' => $doctor->id,
                    'module_key' => $index % 4 === 0 ? 'imaging' : 'laboratory',
                    'order_type' => $index % 4 === 0 ? 'imaging' : 'laboratory',
                    'item_name' => $index % 4 === 0 ? 'Chest X-ray' : 'Full blood count',
                    'status' => $index % 6 === 0 ? 'completed' : 'requested',
                    'requested_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($index % 2 === 1) {
                $invoiceRows[] = [
                    'id' => (string) Str::uuid(),
                    'hospital_id' => $hospital->id,
                    'patient_id' => $patientId,
                    'number' => sprintf('%s-INV-%04d', $hospital->code, $invoiceCursor),
                    'status' => $index % 6 === 1 ? 'paid' : ($index % 6 === 3 ? 'issued' : 'draft'),
                    'total' => 80 + ($index % 9) * 15,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $invoiceCursor++;
            }
        }

        foreach (array_chunk($encounterRows, 200) as $chunk) {
            DB::table('encounters')->insert($chunk);
        }

        $encounterIds = DB::table('encounters')
            ->where('hospital_id', $hospital->id)
            ->whereIn('patient_id', $patientIds)
            ->orderBy('id')
            ->pluck('id', 'patient_id');

        $linkedOrders = [];
        foreach ($orderRows as $row) {
            $row['encounter_id'] = $encounterIds[$row['patient_id']] ?? null;
            $linkedOrders[] = $row;
        }

        foreach (array_chunk($linkedOrders, 200) as $chunk) {
            DB::table('service_orders')->insert($chunk);
        }

        $linkedInvoices = [];
        foreach ($invoiceRows as $row) {
            $row['encounter_id'] = $encounterIds[$row['patient_id']] ?? null;
            $linkedInvoices[] = $row;
        }

        foreach (array_chunk($linkedInvoices, 200) as $chunk) {
            DB::table('invoices')->insert($chunk);
        }
    }
}
