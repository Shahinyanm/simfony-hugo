<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

final class IngestUsageTest extends ApiTestCase
{
    #[Test]
    public function ingestingUsageIncreasesOrganizationConsumption(): void
    {
        $organization = $this->createOrganization(quotaBytes: 10_000_000);
        $this->createCredential($organization);

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/usage', $organization->getApiKey(), [
            'bytes' => 1_000_000,
            'event_id' => 'evt-1',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $this->jsonRequest('GET', '/api/organizations/'.$organization->getId()->toRfc4122(), $organization->getApiKey());

        self::assertResponseIsSuccessful();
        $body = $this->jsonResponse();
        self::assertSame(1_000_000, $body['consumption_bytes']);
        self::assertSame(9_000_000, $body['remaining_bytes']);
        self::assertSame(10_000_000, $body['quota_bytes']);
    }

    #[Test]
    public function ingestingUsageWithoutApiKeyIsUnauthorized(): void
    {
        $organization = $this->createOrganization();

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/usage', null, [
            'bytes' => 1_000_000,
            'event_id' => 'evt-1',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function ingestingUsageWithAnotherOrganizationApiKeyIsForbidden(): void
    {
        $organization = $this->createOrganization();
        $other = $this->createOrganization(name: 'Other');

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/usage', $other->getApiKey(), [
            'bytes' => 1_000_000,
            'event_id' => 'evt-1',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
