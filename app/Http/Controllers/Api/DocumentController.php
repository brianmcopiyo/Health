<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalDocument;
use App\Models\Encounter;
use App\Models\Patient;
use App\Support\Access;
use App\Support\Audit;
use App\Support\FieldCrypt;
use App\Support\TenantRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function index(Request $request, Patient $patient)
    {
        abort_unless(Access::canViewPatient($request->user(), $patient), 403, 'This action is unauthorized.');

        return $patient->documents()
            ->with('uploader:id,name')
            ->latest()
            ->get()
            ->map(fn (ClinicalDocument $document) => $this->serialize($document));
    }

    public function store(Request $request, Patient $patient)
    {
        abort_unless(Access::canUpdatePatient($request->user(), $patient) || $request->user()->hasPermission('update', 'Opd'), 403, 'This action is unauthorized.');

        $allowed = config('hms.upload_mimes');
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('hms.max_upload_kb')],
            'encounter_id' => ['nullable', TenantRules::inHospital('encounters')],
        ]);

        $file = $data['file'];
        $extension = strtolower((string) $file->getClientOriginalExtension());
        abort_unless(in_array($extension, $allowed, true), 422, 'This file type is not allowed.');
        abort_if($file->getSize() < 1, 422, 'The file is empty.');

        $contents = $file->getContent();
        abort_if($contents === false || $contents === '', 422, 'The file could not be read.');

        if (! empty($data['encounter_id'])) {
            $encounter = Encounter::query()->findOrFail($data['encounter_id']);
            abort_unless((int) $encounter->patient_id === (int) $patient->id, 422, 'Encounter does not belong to this patient.');
            abort_unless(Access::canViewEncounter($request->user(), $encounter), 403, 'This action is unauthorized.');
        }

        $uuid = (string) Str::uuid();
        $path = $patient->hospital_id.'/'.$uuid.'.bin';
        Storage::disk('clinical')->put($path, FieldCrypt::encrypt($contents));

        $document = ClinicalDocument::query()->create([
            'uuid' => $uuid,
            'hospital_id' => $patient->hospital_id,
            'patient_id' => $patient->id,
            'encounter_id' => $data['encounter_id'] ?? null,
            'uploaded_by' => $request->user()->id,
            'disk' => 'clinical',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash('sha256', $contents),
            'uploaded_at' => now(),
        ]);

        Audit::record('created', $document, ['patient_id' => $patient->id, 'mime' => $document->mime, 'size' => $document->size]);

        return response()->json($this->serialize($document->load('uploader:id,name')), 201);
    }

    public function download(Request $request, ClinicalDocument $clinicalDocument)
    {
        abort_unless(Access::canViewPatient($request->user(), $clinicalDocument->patient), 403, 'This action is unauthorized.');

        $cipher = Storage::disk($clinicalDocument->disk)->get($clinicalDocument->path);
        abort_unless($cipher, 404, 'File not found.');

        $contents = FieldCrypt::decrypt($cipher);
        $name = str_replace(['"', "\r", "\n"], '', $clinicalDocument->original_name ?: 'document');

        Audit::downloaded($clinicalDocument, ['patient_id' => $clinicalDocument->patient_id]);

        return response($contents, 200, [
            'Content-Type' => $clinicalDocument->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function serialize(ClinicalDocument $document): array
    {
        return [
            'id' => $document->uuid,
            'original_name' => $document->original_name,
            'mime' => $document->mime,
            'size' => $document->size,
            'uploaded_at' => $document->uploaded_at,
            'uploaded_by' => $document->uploader,
        ];
    }
}
