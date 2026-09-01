<?php

declare(strict_types=1);

namespace App\Application\Usage;

use App\Application\Quota\ThresholdNotifier;
use App\Application\UnknownOrganizationException;
use App\Domain\Quota\QuotaGuard;
use App\Entity\Organization;
use App\Message\CredentialsStateChanged;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class UsageApplier
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UsageEventRecorder $recorder,
        private QuotaGuard $quotaGuard,
        private ThresholdNotifier $thresholdNotifier,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function apply(string $organizationId, string $eventId, int $bytes): UsageApplicationResult
    {
        return $this->applyAll($organizationId, [['event_id' => $eventId, 'bytes' => $bytes]])[0];
    }

    /**
     * @param list<array{bytes: int, event_id: string}> $events
     *
     * @return array<string, int>
     */
    public function applyBatch(string $organizationId, array $events): array
    {
        $outcome = [];
        foreach ($this->applyAll($organizationId, $events) as $result) {
            $outcome[$result->value] = ($outcome[$result->value] ?? 0) + 1;
        }

        return $outcome;
    }

    /**
     * @param list<array{bytes: int, event_id: string}> $events
     *
     * @return list<UsageApplicationResult>
     */
    private function applyAll(string $organizationId, array $events): array
    {
        return $this->entityManager->wrapInTransaction(
            function () use ($organizationId, $events): array {
                $organization = $this->entityManager->find(
                    Organization::class,
                    $organizationId,
                    LockMode::PESSIMISTIC_WRITE,
                );

                if (null === $organization) {
                    throw new UnknownOrganizationException($organizationId);
                }

                $results = [];
                foreach ($events as $event) {
                    $results[] = $this->record($organization, $event['event_id'], $event['bytes']);
                }

                $change = $this->quotaGuard->synchronize($organization);
                if (null !== $change) {
                    $this->messageBus->dispatch(CredentialsStateChanged::fromChange($change));
                }

                $this->thresholdNotifier->notifyCrossedThresholds($organization);

                return $results;
            },
        );
    }

    private function record(Organization $organization, string $eventId, int $bytes): UsageApplicationResult
    {
        $status = $organization->isOverQuota()
            ? UsageEventStatus::RejectedOverQuota
            : UsageEventStatus::Applied;

        if (!$this->recorder->record($organization->getId()->toRfc4122(), $eventId, $bytes, $status)) {
            return UsageApplicationResult::Duplicate;
        }

        if (UsageEventStatus::RejectedOverQuota === $status) {
            return UsageApplicationResult::RejectedOverQuota;
        }

        $organization->addConsumption($bytes);

        return UsageApplicationResult::Applied;
    }
}
