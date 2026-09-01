<?php

namespace App\Support;

class HttpErrorProbe
{
    public function __invoke(): never
    {
        abort_unless(app()->environment('local', 'testing') && config('app.http_error_probe'), 404);
        throw new \RuntimeException('http-error-probe');
    }
}
