<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

final class PostTopupTest extends ApiTestCase
{
    #[Test]
    public function topupIncreasesQuotaWithoutChangingConsumption(): void
    {
        $organization = $this->createOrganization(quotaBytes: 10_000_000);
        $this->createCredential($organization);

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/topups', $organization->getApiKey(), [
            'bytes' => 5_000_000,
        ]);

        self::assertResponseIsSuccessful();
        $body = $this->jsonResponse();
        self::assertSame(15_000_000, $body['quota_bytes']);
        self::assertSame(0, $body['consumption_bytes']);
        self::assertSame(15_000_000, $body['remaining_bytes']);
    }

    #[Test]
    public function topupOnHealthyOrganizationDoesNotTouchCredentials(): void
    {
        $organization = $this->createOrganization(quotaBytes: 10_000_000);
        $credentialId = $this->createCredential($organization)->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/topups', $organization->getApiKey(), [
            'bytes' => 5_000_000,
        ]);

        $outbox = static::getContainer()->get('messenger.transport.outbox');
        self::assertCount(0, $outbox->getSent());

        $this->jsonRequest('GET', '/api/credentials/'.$credentialId, $organization->getApiKey());
        self::assertTrue($this->jsonResponse()['enabled']);
    }

    #[Test]
    public function topupWithAnotherOrganizationApiKeyIsForbidden(): void
    {
        $organization = $this->createOrganization();
        $other = $this->createOrganization(name: 'Other');

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/topups', $other->getApiKey(), [
            'bytes' => 5_000_000,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
