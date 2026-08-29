<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DashboardBuilder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function command(Request $request)
    {
        return response()->json((new DashboardBuilder($request->user()))->payload());
    }
}
