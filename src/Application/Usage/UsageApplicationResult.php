<?php

declare(strict_types=1);

namespace App\Application\Usage;

enum UsageApplicationResult: string
{
    case Applied = 'applied';
    case Duplicate = 'duplicate';
    case RejectedOverQuota = 'rejected_over_quota';
}
