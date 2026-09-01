<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\UnknownOrganizationException;
use App\Application\Usage\UsageApplier;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

final class FailedIngestionTest extends ApiTestCase
{
    #[Test]
    public function usageForUnknownOrganizationFails(): void
    {
        $applier = static::getContainer()->get(UsageApplier::class);
        self::assertInstanceOf(UsageApplier::class, $applier);

        $this->expectException(UnknownOrganizationException::class);

        $applier->apply(Uuid::v7()->toRfc4122(), 'evt-orphan', 1_000);
    }
}
