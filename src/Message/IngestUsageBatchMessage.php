<?php

declare(strict_types=1);

namespace App\Message;

final readonly class IngestUsageBatchMessage
{
    /**
     * @param list<array{bytes: int, event_id: string}> $events
     */
    public function __construct(
        public string $organizationId,
        public array $events,
    ) {
    }
}
