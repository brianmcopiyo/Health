<?php

namespace App\Support\Reports;

class PdfWriter
{
    public static function build(array $document): string
    {
        return PdfEngine::render($document);
    }
}
