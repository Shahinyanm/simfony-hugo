<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Application\Usage\UsageApplier;
use App\Message\IngestUsageBatchMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IngestUsageBatchMessageHandler
{
    public function __construct(
        private UsageApplier $applier,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(IngestUsageBatchMessage $message): void
    {
        $outcome = $this->applier->applyBatch($message->organizationId, $message->events);

        $this->logger->info('usage batch processed', [
            'organization_id' => $message->organizationId,
            'events' => \count($message->events),
            'outcome' => $outcome,
        ]);
    }
}
