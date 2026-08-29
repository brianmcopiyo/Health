<?php

namespace App\Console\Commands;

use App\Models\ClinicalDocument;
use App\Models\Patient;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredRecords extends Command
{
    protected $signature = 'hms:purge-expired';

    protected $description = 'Securely purge patient records past retention';

    public function handle(): int
    {
        $count = 0;

        Patient::withoutGlobalScopes()
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', now())
            ->whereNull('purged_at')
            ->orderBy('id')
            ->chunkById(50, function ($patients) use (&$count) {
                foreach ($patients as $patient) {
                    ClinicalDocument::withoutGlobalScopes()
                        ->where('patient_id', $patient->id)
                        ->get()
                        ->each(function (ClinicalDocument $document) {
                            Storage::disk($document->disk)->delete($document->path);
                            $document->delete();
                        });

                    $patient->forceFill([
                        'phone' => null,
                        'email' => null,
                        'national_id' => null,
                        'address' => null,
                        'emergency_contact_name' => null,
                        'emergency_contact_phone' => null,
                        'next_of_kin_name' => null,
                        'next_of_kin_phone' => null,
                        'notes' => null,
                        'first_name' => 'REDACTED',
                        'last_name' => 'REDACTED',
                        'archived_at' => $patient->archived_at ?? now(),
                        'purged_at' => now(),
                    ])->save();

                    Audit::record('purged', $patient, ['mrn' => $patient->mrn]);
                    $count++;
                }
            });

        $this->info('Purged '.$count.' patient records.');

        return self::SUCCESS;
    }
}
