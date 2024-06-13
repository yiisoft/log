<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\TestAsset;

use Yiisoft\Log\ContextEnricher\ContextEnricherInterface;

final class StubContextEnricher implements ContextEnricherInterface
{
    public function process(array $context): array
    {
        return $context;
    }
}
