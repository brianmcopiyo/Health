<?php

namespace App\Console\Commands;

use App\Support\FieldCrypt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RotateEncryption extends Command
{
    protected $signature = 'hms:rotate-encryption';

    protected $description = 'Re-encrypt sensitive fields with the current encryption key';

    public function handle(): int
    {
        $count = 0;

        foreach (FieldCrypt::targets() as $table => $columns) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->chunkById(100, function ($rows) use ($table, $columns, &$count) {
                foreach ($rows as $row) {
                    $update = [];
                    foreach ($columns as $column) {
                        $value = $row->{$column} ?? null;
                        if (! FieldCrypt::isEncrypted($value)) {
                            continue;
                        }
                        $update[$column] = FieldCrypt::reencrypt($value);
                    }
                    if ($update) {
                        DB::table($table)->where('id', $row->id)->update($update);
                        $count++;
                    }
                }
            });
        }

        $this->info('Re-encrypted '.$count.' records.');

        return self::SUCCESS;
    }
}
