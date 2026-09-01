<?php

declare(strict_types=1);

namespace App\Application\Usage;

enum UsageEventStatus: string
{
    case Applied = 'applied';
    case RejectedOverQuota = 'rejected_over_quota';
}
