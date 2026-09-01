<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Credential;
use App\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function createOrganization(
        string $name = 'Acme',
        int $quotaBytes = 10_000_000,
        ?string $apiKey = null,
    ): Organization {
        $organization = new Organization($name, $apiKey ?? \bin2hex(\random_bytes(16)), $quotaBytes);
        $this->entityManager->persist($organization);
        $this->entityManager->flush();

        return $organization;
    }

    protected function createCredential(Organization $organization, string $username = 'proxy-1'): Credential
    {
        $credential = new Credential($organization, $username);
        $this->entityManager->persist($credential);
        $this->entityManager->flush();

        return $credential;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function jsonRequest(string $method, string $uri, ?string $apiKey, array $payload = []): void
    {
        $headers = [
            'HTTP_ACCEPT' => 'application/json',
        ];
        if (null !== $apiKey) {
            $headers['HTTP_X_API_KEY'] = $apiKey;
        }
        if ([] !== $payload) {
            $headers['CONTENT_TYPE'] = 'application/json';
        }

        $this->client->request(
            $method,
            $uri,
            server: $headers,
            content: [] !== $payload ? \json_encode($payload, \JSON_THROW_ON_ERROR) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertNotFalse($content);
        $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $messageClass
     *
     * @return list<T>
     */
    protected function sentToOutbox(string $messageClass): array
    {
        $messages = [];
        foreach (static::getContainer()->get('messenger.transport.outbox')->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof $messageClass) {
                $messages[] = $message;
            }
        }

        return $messages;
    }
}
