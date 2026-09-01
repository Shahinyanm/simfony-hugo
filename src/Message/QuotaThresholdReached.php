<?php

declare(strict_types=1);

namespace App\Message;

final readonly class QuotaThresholdReached
{
    public function __construct(
        public string $organizationId,
        public int $thresholdPercent,
        public int $consumptionBytes,
        public int $quotaBytes,
        public string $occurredAt,
    ) {
    }
}
