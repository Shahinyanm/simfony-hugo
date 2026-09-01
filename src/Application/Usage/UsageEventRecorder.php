<?php

declare(strict_types=1);

namespace App\Application\Usage;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

final readonly class UsageEventRecorder
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    public function record(
        string $organizationId,
        string $eventId,
        int $bytes,
        UsageEventStatus $status,
    ): bool {
        $inserted = $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO usage_events (id, organization_id, event_id, bytes, status, received_at)
                VALUES (:id, :organization_id, :event_id, :bytes, :status, :received_at)
                ON CONFLICT (organization_id, event_id) DO NOTHING
                SQL,
            [
                'id' => Uuid::v7()->toRfc4122(),
                'organization_id' => $organizationId,
                'event_id' => $eventId,
                'bytes' => $bytes,
                'status' => $status->value,
                'received_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            ],
        );

        return $inserted > 0;
    }
}
