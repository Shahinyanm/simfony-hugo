<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Credential;
use App\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:demo:seed', description: 'Create a demo organization with two proxy credentials')]
final class DemoSeedCommand extends Command
{
    public const string DEMO_API_KEY = 'demo-acme-key';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $existing = $this->entityManager->getRepository(Organization::class)->findOneBy(['apiKey' => self::DEMO_API_KEY]);
        if (null !== $existing) {
            $io->warning('Demo organization already exists. Nothing to do.');
            $this->printOrganization($io, $existing);

            return Command::SUCCESS;
        }

        $organization = new Organization('Acme', self::DEMO_API_KEY);
        new Credential($organization, 'acme-proxy-1');
        new Credential($organization, 'acme-proxy-2');

        $this->entityManager->persist($organization);
        $this->entityManager->flush();

        $io->success('Demo organization created.');
        $this->printOrganization($io, $organization);

        return Command::SUCCESS;
    }

    private function printOrganization(SymfonyStyle $io, Organization $organization): void
    {
        $io->definitionList(
            ['Organization id' => $organization->getId()->toRfc4122()],
            ['Name' => $organization->getName()],
            ['API key' => $organization->getApiKey()],
            ['Quota (bytes)' => (string) $organization->getQuotaBytes()],
        );

        $rows = [];
        foreach ($organization->getCredentials() as $credential) {
            $rows[] = [
                $credential->getUsername(),
                $credential->getId()->toRfc4122(),
                $credential->isEnabled() ? 'yes' : 'no',
            ];
        }
        $io->table(['Username', 'Credential id', 'Enabled'], $rows);
        $io->writeln('Example: curl -H "X-Api-Key: demo-acme-key" http://localhost:8080/api/organizations/'.$organization->getId()->toRfc4122());
    }
}
