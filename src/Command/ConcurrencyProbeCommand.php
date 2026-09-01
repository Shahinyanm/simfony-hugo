<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Topup\TopupApplier;
use App\Application\Usage\UsageApplier;
use App\Entity\Credential;
use App\Entity\Organization;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:concurrency:probe',
    description: 'Applies usage from parallel processes and verifies the quota invariant holds',
)]
final class ConcurrencyProbeCommand extends Command
{
    private const string ORGANIZATION_NAME = 'Concurrency probe';
    private const int CREDENTIALS = 3;
    private const int QUOTA_BYTES = 1_000_000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly UsageApplier $usageApplier,
        private readonly TopupApplier $topupApplier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('workers', null, InputOption::VALUE_REQUIRED, 'Parallel usage processes', '8')
            ->addOption('events', null, InputOption::VALUE_REQUIRED, 'Events per process', '25')
            ->addOption('topups', null, InputOption::VALUE_REQUIRED, 'Parallel top-up processes', '2')
            ->addOption('worker-for', null, InputOption::VALUE_REQUIRED, 'Internal: organization to apply usage to')
            ->addOption('topup-for', null, InputOption::VALUE_REQUIRED, 'Internal: organization to top up')
            ->addOption('slot', null, InputOption::VALUE_REQUIRED, 'Internal: worker index', '0')
            ->addOption('bytes', null, InputOption::VALUE_REQUIRED, 'Internal: bytes per event', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null !== $input->getOption('worker-for')) {
            return $this->runUsageWorker(
                $input->getOption('worker-for'),
                (int) $input->getOption('slot'),
                (int) $input->getOption('events'),
                (int) $input->getOption('bytes'),
            );
        }

        if (null !== $input->getOption('topup-for')) {
            return $this->runTopupWorker($input->getOption('topup-for'), (int) $input->getOption('events'));
        }

        return $this->coordinate(
            new SymfonyStyle($input, $output),
            (int) $input->getOption('workers'),
            (int) $input->getOption('events'),
            (int) $input->getOption('topups'),
        );
    }

    private function coordinate(SymfonyStyle $io, int $workers, int $events, int $topups): int
    {
        $organization = $this->createProbeOrganization();
        $organizationId = $organization->getId()->toRfc4122();
        $bytesPerEvent = $this->bytesPerEvent($workers, $events);

        $io->section(\sprintf('%d usage workers x %d events + %d top-up workers', $workers, $events, $topups));

        $processes = [];
        for ($slot = 0; $slot < $workers; ++$slot) {
            $processes[] = $this->spawn([
                '--worker-for='.$organizationId,
                '--slot='.$slot,
                '--events='.$events,
                '--bytes='.$bytesPerEvent,
            ]);
        }

        for ($slot = 0; $slot < $topups; ++$slot) {
            $processes[] = $this->spawn([
                '--topup-for='.$organizationId,
                '--events='.$events,
            ]);
        }

        foreach ($processes as $process) {
            $process->start();
        }

        $failed = 0;
        foreach ($processes as $process) {
            $process->wait();
            if (!$process->isSuccessful()) {
                ++$failed;
                $io->writeln('<comment>'.\trim($process->getErrorOutput()).'</comment>');
            }
        }

        return $this->verify($io, $organizationId, $workers * $events, $failed);
    }

    private function spawn(array $arguments): Process
    {
        return new Process(
            \array_merge(['php', 'bin/console', 'app:concurrency:probe'], $arguments),
            \dirname(__DIR__, 2),
            null,
            null,
            300.0,
        );
    }

    private function bytesPerEvent(int $workers, int $events): int
    {
        return (int) \max(1, \floor(self::QUOTA_BYTES * 2 / ($workers * $events)));
    }

    private function runUsageWorker(string $organizationId, int $slot, int $events, int $bytesPerEvent): int
    {
        for ($i = 0; $i < $events; ++$i) {
            $this->usageApplier->apply($organizationId, \sprintf('w%d-e%d', $slot, $i), $bytesPerEvent);
            $this->entityManager->clear();
        }

        return Command::SUCCESS;
    }

    private function runTopupWorker(string $organizationId, int $rounds): int
    {
        for ($i = 0; $i < $rounds; ++$i) {
            $this->topupApplier->apply($organizationId, 1_000);
            $this->entityManager->clear();
        }

        return Command::SUCCESS;
    }

    private function createProbeOrganization(): Organization
    {
        $this->connection->executeStatement('DELETE FROM organizations WHERE name = ?', [self::ORGANIZATION_NAME]);

        $organization = new Organization(self::ORGANIZATION_NAME, 'probe-'.\bin2hex(\random_bytes(8)), self::QUOTA_BYTES);
        for ($i = 1; $i <= self::CREDENTIALS; ++$i) {
            new Credential($organization, 'probe-proxy-'.$i);
        }

        $this->entityManager->persist($organization);
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $organization;
    }

    private function verify(SymfonyStyle $io, string $organizationId, int $expectedEvents, int $failedProcesses): int
    {
        $violations = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*) FROM credentials c
                JOIN organizations o ON o.id = c.organization_id
                WHERE c.organization_id = ? AND c.enabled = true AND o.consumption_bytes >= o.quota_bytes
                SQL,
            [$organizationId],
        );

        $totals = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT o.consumption_bytes,
                       o.quota_bytes,
                       COALESCE((SELECT SUM(bytes) FROM usage_events WHERE organization_id = o.id AND status = 'applied'), 0) AS applied_bytes,
                       (SELECT COUNT(*) FROM usage_events WHERE organization_id = o.id) AS rows_total,
                       (SELECT COUNT(DISTINCT event_id) FROM usage_events WHERE organization_id = o.id) AS rows_distinct
                FROM organizations o WHERE o.id = ?
                SQL,
            [$organizationId],
        );

        $lostUpdates = (int) $totals['consumption_bytes'] !== (int) $totals['applied_bytes'];
        $duplicates = (int) $totals['rows_total'] !== (int) $totals['rows_distinct'];
        $missing = (int) $totals['rows_total'] !== $expectedEvents;

        $io->table(['check', 'result'], [
            ['credentials enabled while over quota', 0 === $violations ? 'ok (0)' : 'VIOLATED ('.$violations.')'],
            ['consumption equals applied events', $lostUpdates ? 'LOST UPDATES ('.$totals['consumption_bytes'].' != '.$totals['applied_bytes'].')' : 'ok ('.$totals['consumption_bytes'].')'],
            ['every event recorded once', $duplicates ? 'DUPLICATES' : 'ok ('.$totals['rows_distinct'].')'],
            ['all events reached the ledger', $missing ? 'MISSING ('.$totals['rows_total'].' of '.$expectedEvents.')' : 'ok ('.$expectedEvents.')'],
            ['worker processes failed', 0 === $failedProcesses ? 'ok (0)' : 'FAILED ('.$failedProcesses.')'],
        ]);

        if (0 !== $violations || $lostUpdates || $duplicates || $missing || 0 !== $failedProcesses) {
            $io->error('Concurrency invariant does not hold.');

            return Command::FAILURE;
        }

        $io->success('Invariant holds under parallel usage and top-ups.');

        return Command::SUCCESS;
    }
}
