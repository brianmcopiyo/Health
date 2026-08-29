<?php

namespace App\Console\Commands;

use App\Support\FieldCrypt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class EncryptedBackup extends Command
{
    protected $signature = 'hms:backup';

    protected $description = 'Create an encrypted application backup';

    public function handle(): int
    {
        $connection = config('database.default');
        $database = config('database.connections.'.$connection.'.database');
        $payload = '';

        if ($connection === 'sqlite' && is_string($database) && $database !== ':memory:' && File::exists($database)) {
            $payload = File::get($database);
        } else {
            $payload = json_encode([
                'connection' => $connection,
                'exported_at' => now()->toIso8601String(),
                'note' => 'Use the database vendor backup tool and encrypt the dump with HMS_ENCRYPTION_KEY.',
            ]);
        }

        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory, 0700);
        $path = $directory.'/hms-'.now()->format('YmdHis').'.bak';
        File::put($path, FieldCrypt::encrypt($payload));
        @chmod($path, 0600);

        $this->info('Encrypted backup written to '.$path);

        return self::SUCCESS;
    }
}
