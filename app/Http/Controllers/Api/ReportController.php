<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Reports\ReportBuilder;
use App\Support\Reports\ReportCriteria;
use App\Support\Reports\ReportExport;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function meta(Request $request)
    {
        return response()->json(ReportBuilder::meta($request->user()));
    }

    public function show(Request $request)
    {
        $criteria = ReportCriteria::fromRequest($request);

        return response()->json((new ReportBuilder($request->user(), $criteria))->payload());
    }

    public function table(Request $request)
    {
        $criteria = ReportCriteria::fromRequest($request);

        return response()->json((new ReportBuilder($request->user(), $criteria))->table());
    }

    public function export(Request $request)
    {
        $criteria = ReportCriteria::fromRequest($request);
        $format = strtolower((string) $request->string('format', 'xlsx'));

        return ReportExport::download($request->user(), $criteria, $format);
    }
}
