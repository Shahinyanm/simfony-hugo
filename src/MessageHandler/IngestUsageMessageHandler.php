<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Organization;
use App\Message\IngestUsageMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IngestUsageMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(IngestUsageMessage $message): void
    {
        $organization = $this->entityManager->find(Organization::class, $message->organizationId);
        if (null === $organization) {
            throw new \RuntimeException(\sprintf('Organization "%s" not found.', $message->organizationId));
        }

        $organization->addConsumption($message->bytes);
        $this->entityManager->flush();
    }
}
