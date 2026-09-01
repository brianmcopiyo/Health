<?php

use App\Support\HttpErrorProbe;
use Illuminate\Support\Facades\Route;

Route::get('__http-error-probe', HttpErrorProbe::class);

Route::get('build/{path}', function (string $path) {
    abort_if(str_starts_with($path, 'assets/'), 404);

    return redirect('/'.ltrim($path, '/'));
})->where('path', '.*');

Route::get('{any?}', function () {
    return view('application');
})->where('any', '^(?!api(?:/|$)).*');
