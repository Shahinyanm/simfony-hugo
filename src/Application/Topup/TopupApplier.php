<?php

declare(strict_types=1);

namespace App\Application\Topup;

use App\Application\UnknownOrganizationException;
use App\Domain\Quota\QuotaGuard;
use App\Entity\Organization;
use App\Message\CredentialsStateChanged;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class TopupApplier
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuotaGuard $quotaGuard,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function apply(string $organizationId, int $bytes): Organization
    {
        return $this->entityManager->wrapInTransaction(
            function () use ($organizationId, $bytes): Organization {
                $organization = $this->entityManager->find(
                    Organization::class,
                    $organizationId,
                    LockMode::PESSIMISTIC_WRITE,
                );

                if (null === $organization) {
                    throw new UnknownOrganizationException($organizationId);
                }

                $organization->addQuota($bytes);

                $change = $this->quotaGuard->synchronize($organization);
                if (null !== $change) {
                    $this->messageBus->dispatch(CredentialsStateChanged::fromChange($change));
                }

                return $organization;
            },
        );
    }
}
