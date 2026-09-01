<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

final class BrokerUnavailableTest extends ApiTestCase
{
    private function breakTheBus(): void
    {
        static::getContainer()->set(MessageBusInterface::class, new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                throw new TransportException('Could not connect to the AMQP server.');
            }
        });
    }

    #[Test]
    public function usageIngestionAnswersServiceUnavailable(): void
    {
        $organization = $this->createOrganization();
        $this->breakTheBus();

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/usage', $organization->getApiKey(), [
            'bytes' => 1_000,
            'event_id' => 'evt-1',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        self::assertSame('5', $this->client->getResponse()->headers->get('Retry-After'));
    }

    #[Test]
    public function batchIngestionAnswersServiceUnavailable(): void
    {
        $organization = $this->createOrganization();
        $this->breakTheBus();

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/usage/batch', $organization->getApiKey(), [
            'events' => [['bytes' => 1_000, 'event_id' => 'evt-1']],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
    }

    #[Test]
    public function readingOrganizationStillWorksWhileTheBrokerIsDown(): void
    {
        $organization = $this->createOrganization();
        $this->breakTheBus();

        $this->jsonRequest('GET', '/api/organizations/'.$organization->getId()->toRfc4122(), $organization->getApiKey());

        self::assertResponseIsSuccessful();
    }
}
