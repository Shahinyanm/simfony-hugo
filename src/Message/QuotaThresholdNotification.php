<?php

declare(strict_types=1);

namespace App\Message;

final readonly class QuotaThresholdNotification
{
    public function __construct(
        public string $organizationId,
        public int $thresholdPercent,
        public int $consumptionBytes,
        public int $quotaBytes,
        public string $occurredAt,
    ) {
    }

    public static function fromDomainEvent(QuotaThresholdReached $event): self
    {
        return new self(
            organizationId: $event->organizationId,
            thresholdPercent: $event->thresholdPercent,
            consumptionBytes: $event->consumptionBytes,
            quotaBytes: $event->quotaBytes,
            occurredAt: $event->occurredAt,
        );
    }
}
