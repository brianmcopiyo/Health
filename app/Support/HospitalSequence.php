<?php

namespace App\Support;

use App\Models\Hospital;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HospitalSequence
{
    public static function nextMrn(Hospital $hospital): string
    {
        return sprintf('%s-%04d', self::prefix($hospital), self::bump($hospital, 'patient_seq'));
    }

    public static function nextInvoiceNumber(Hospital $hospital): string
    {
        return sprintf('%s-INV-%04d', self::prefix($hospital), self::bump($hospital, 'invoice_seq'));
    }

    public static function prefix(Hospital $hospital): string
    {
        $fromWords = collect(preg_split('/\s+/', trim((string) $hospital->name)))
            ->filter()
            ->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');

        return $fromWords !== '' ? $fromWords : 'HMS';
    }

    public static function bump(Hospital $hospital, string $column, int $count = 1): int
    {
        return DB::transaction(function () use ($hospital, $column, $count) {
            $row = Hospital::query()->whereKey($hospital->id)->lockForUpdate()->firstOrFail();
            $start = (int) $row->{$column} + 1;
            $row->{$column} = (int) $row->{$column} + $count;
            $row->save();
            $hospital->{$column} = $row->{$column};

            return $start;
        });
    }

    public static function sync(Hospital $hospital): void
    {
        $patients = DB::table('patients')->where('hospital_id', $hospital->id)->count();
        $invoices = DB::table('invoices')->where('hospital_id', $hospital->id)->count();
        $hospital->patient_seq = max((int) $hospital->patient_seq, $patients);
        $hospital->invoice_seq = max((int) $hospital->invoice_seq, $invoices);
        $hospital->save();
    }
}
