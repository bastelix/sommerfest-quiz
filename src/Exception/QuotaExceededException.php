<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public readonly string $metric;
    public readonly string $plan;
    public readonly int $currentValue;
    public readonly int $maxValue;

    public function __construct(string $metric, string $plan, int $currentValue, int $maxValue)
    {
        $this->metric = $metric;
        $this->plan = $plan;
        $this->currentValue = $currentValue;
        $this->maxValue = $maxValue;

        parent::__construct(sprintf(
            'Quota exceeded for metric "%s" on plan "%s": %d/%d',
            $metric,
            $plan,
            $currentValue,
            $maxValue
        ));
    }
}
