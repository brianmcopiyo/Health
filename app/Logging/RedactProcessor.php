<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RedactProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->redact($record->context);
        $message = $this->mask((string) $record->message);

        return $record->with(message: $message, context: $context);
    }

    private function redact(array $context): array
    {
        $blocked = ['password', 'token', 'accessToken', 'national_id', 'phone', 'email', 'authorization', 'cookie'];
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true) || str_contains(strtolower((string) $key), 'password')) {
                $context[$key] = '[redacted]';
                continue;
            }
            if (is_array($value)) {
                $context[$key] = $this->redact($value);
            } elseif (is_string($value)) {
                $context[$key] = $this->mask($value);
            }
        }

        return $context;
    }

    private function mask(string $value): string
    {
        $value = preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/', 'Bearer [redacted]', $value) ?? $value;

        return $value;
    }
}
