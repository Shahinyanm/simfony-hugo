<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Message\QuotaThresholdReached;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

final class QuotaThresholdTest extends ApiTestCase
{
    /**
     * @return list<QuotaThresholdReached>
     */
    private function thresholdEvents(): array
    {
        return $this->sentToOutbox(QuotaThresholdReached::class);
    }

    #[Test]
    public function usageBelowTheThresholdNotifiesNothing(): void
    {
        $organization = $this->createOrganization(quotaBytes: 1_000_000);

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/usage', $organization->getApiKey(), [
            'bytes' => 700_000,
            'event_id' => 'evt-1',
        ]);

        self::assertSame([], $this->thresholdEvents());
    }

    #[Test]
    public function crossingTheThresholdNotifiesOnce(): void
    {
        $organization = $this->createOrganization(quotaBytes: 1_000_000);

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/usage', $organization->getApiKey(), [
            'bytes' => 800_000,
            'event_id' => 'evt-1',
        ]);

        $events = $this->thresholdEvents();
        self::assertCount(1, $events);
        self::assertSame(80, $events[0]->thresholdPercent);
        self::assertSame($organization->getId()->toRfc4122(), $events[0]->organizationId);
    }

    #[Test]
    public function furtherUsageAboveTheThresholdNotifiesNothing(): void
    {
        $organization = $this->createOrganization(quotaBytes: 1_000_000);
        $uri = '/api/organizations/'.$organization->getId()->toRfc4122().'/usage';

        $this->jsonRequest('POST', $uri, $organization->getApiKey(), ['bytes' => 800_000, 'event_id' => 'evt-1']);
        self::assertCount(1, $this->thresholdEvents());

        for ($i = 2; $i <= 5; ++$i) {
            $this->jsonRequest('POST', $uri, $organization->getApiKey(), ['bytes' => 10_000, 'event_id' => 'evt-'.$i]);
            self::assertSame([], $this->thresholdEvents());
        }
    }

    #[Test]
    public function topupBelowTheThresholdRearmsTheNotification(): void
    {
        $organization = $this->createOrganization(quotaBytes: 1_000_000);
        $id = $organization->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/organizations/'.$id.'/usage', $organization->getApiKey(), ['bytes' => 800_000, 'event_id' => 'evt-1']);
        self::assertCount(1, $this->thresholdEvents());

        $this->jsonRequest('POST', '/api/organizations/'.$id.'/topups', $organization->getApiKey(), ['bytes' => 1_000_000]);

        $this->jsonRequest('POST', '/api/organizations/'.$id.'/usage', $organization->getApiKey(), ['bytes' => 800_000, 'event_id' => 'evt-2']);
        self::assertCount(1, $this->thresholdEvents());
    }
}
