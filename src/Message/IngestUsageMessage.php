<?php

declare(strict_types=1);

namespace App\Message;

final readonly class IngestUsageMessage
{
    public function __construct(
        public string $organizationId,
        public int $bytes,
        public string $eventId,
    ) {
    }
}
