<?php

declare(strict_types=1);

namespace App\Application\Usage;

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
        private MessageBusInterface $messageBus,
    ) {
    }

    public function apply(string $organizationId, string $eventId, int $bytes): UsageApplicationResult
    {
        return $this->entityManager->wrapInTransaction(
            function () use ($organizationId, $eventId, $bytes): UsageApplicationResult {
                $organization = $this->entityManager->find(
                    Organization::class,
                    $organizationId,
                    LockMode::PESSIMISTIC_WRITE,
                );

                if (null === $organization) {
                    throw new UnknownOrganizationException($organizationId);
                }

                $status = $organization->isOverQuota()
                    ? UsageEventStatus::RejectedOverQuota
                    : UsageEventStatus::Applied;

                if (!$this->recorder->record($organizationId, $eventId, $bytes, $status)) {
                    return UsageApplicationResult::Duplicate;
                }

                if (UsageEventStatus::RejectedOverQuota === $status) {
                    return UsageApplicationResult::RejectedOverQuota;
                }

                $organization->addConsumption($bytes);

                $change = $this->quotaGuard->synchronize($organization);
                if (null !== $change) {
                    $this->messageBus->dispatch(CredentialsStateChanged::fromChange($change));
                }

                return UsageApplicationResult::Applied;
            },
        );
    }
}
