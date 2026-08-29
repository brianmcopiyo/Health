<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Exports\DatasetExport;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function show(Request $request, string $dataset)
    {
        return DatasetExport::download($request->user(), $dataset, $request);
    }
}
