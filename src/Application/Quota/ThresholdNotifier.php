<?php

declare(strict_types=1);

namespace App\Application\Quota;

use App\Entity\Organization;
use App\Message\QuotaThresholdReached;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ThresholdNotifier
{
    private const array THRESHOLDS = [80];

    public function __construct(
        private Connection $connection,
        private MessageBusInterface $messageBus,
        private ClockInterface $clock,
    ) {
    }

    public function notifyCrossedThresholds(Organization $organization): void
    {
        $percent = $this->usedPercent($organization);

        foreach (self::THRESHOLDS as $threshold) {
            if ($percent < $threshold) {
                continue;
            }

            if (!$this->markNotified($organization, $threshold)) {
                continue;
            }

            $this->messageBus->dispatch(new QuotaThresholdReached(
                organizationId: $organization->getId()->toRfc4122(),
                thresholdPercent: $threshold,
                consumptionBytes: $organization->getConsumptionBytes(),
                quotaBytes: $organization->getQuotaBytes(),
                occurredAt: $this->clock->now()->format(\DATE_ATOM),
            ));
        }
    }

    public function rearmThresholdsAbove(Organization $organization): void
    {
        $this->connection->executeStatement(
            'DELETE FROM organization_quota_notifications WHERE organization_id = :id AND threshold_percent > :percent',
            [
                'id' => $organization->getId()->toRfc4122(),
                'percent' => $this->usedPercent($organization),
            ],
        );
    }

    private function usedPercent(Organization $organization): int
    {
        $quota = $organization->getQuotaBytes();
        if ($quota < 1) {
            return 100;
        }

        return (int) \floor($organization->getConsumptionBytes() * 100 / $quota);
    }

    private function markNotified(Organization $organization, int $threshold): bool
    {
        return $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO organization_quota_notifications (organization_id, threshold_percent, notified_at)
                VALUES (:id, :percent, :notified_at)
                ON CONFLICT (organization_id, threshold_percent) DO NOTHING
                SQL,
            [
                'id' => $organization->getId()->toRfc4122(),
                'percent' => $threshold,
                'notified_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            ],
        ) > 0;
    }
}
