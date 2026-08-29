<?php

namespace App\Support\Reports;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ReportExport
{
    public static function download(User $user, ReportCriteria $criteria, string $format): Response
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 422, 'Unsupported export format.');
        abort_unless(ReportCatalog::allows($user, $criteria->section), 403, 'This action is unauthorized.');

        $criteria->scope = 'complete';
        $document = ReportComposer::make($user, $criteria);

        if ($user->hospital) {
            Audit::exported($user->hospital, [
                'section' => $criteria->section,
                'scope' => $criteria->scope,
                'format' => $format,
                'from' => $criteria->from->toDateString(),
                'to' => $criteria->to->toDateString(),
                'modules' => $document['meta']['modules'] ?? [$criteria->section],
            ]);
        }

        $filename = self::filename($document, $format);

        if ($format === 'xlsx') {
            return response(XlsxEngine::render($document), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        return response(PdfEngine::render($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private static function filename(array $document, string $format): string
    {
        $code = $document['meta']['organization']['code'] ?? 'HMS';
        $title = $document['meta']['title'] ?? 'report';
        $from = $document['meta']['period']['from'] ?? '';
        $to = $document['meta']['period']['to'] ?? '';

        return Str::slug($code.'-'.$title.'-'.$from.'-'.$to).'.'.$format;
    }
}
