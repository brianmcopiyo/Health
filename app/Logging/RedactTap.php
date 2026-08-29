<?php

namespace App\Logging;

class RedactTap
{
    public function __invoke($logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(new RedactProcessor);
        }
    }
}
