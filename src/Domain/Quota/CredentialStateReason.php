<?php

declare(strict_types=1);

namespace App\Domain\Quota;

enum CredentialStateReason: string
{
    case QuotaExhausted = 'quota_exhausted';
    case QuotaRestored = 'quota_restored';
}
