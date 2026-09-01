<?php

declare(strict_types=1);

namespace App\Tests\Concurrency;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ConcurrentUsageTest extends TestCase
{
    private const string PROJECT_DIR = __DIR__.'/../..';

    protected function tearDown(): void
    {
        $process = new Process(
            ['php', 'bin/console', 'dbal:run-sql', "DELETE FROM organizations WHERE name = 'Concurrency probe'"],
            self::PROJECT_DIR,
        );
        $process->run();

        parent::tearDown();
    }

    #[Test]
    public function parallelUsageAndTopupsKeepTheQuotaInvariant(): void
    {
        $probe = new Process(
            ['php', 'bin/console', 'app:concurrency:probe', '--workers=8', '--events=25', '--topups=2'],
            self::PROJECT_DIR,
            ['APP_ENV' => 'dev'],
            null,
            300.0,
        );

        $probe->run();

        self::assertTrue(
            $probe->isSuccessful(),
            'Concurrency probe reported a violation:'.\PHP_EOL.$probe->getOutput(),
        );
        self::assertStringContainsString('Invariant holds', $probe->getOutput());
    }
}
