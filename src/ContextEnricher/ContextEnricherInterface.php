<?php

declare(strict_types=1);

namespace Yiisoft\Log\ContextEnricher;

/**
 * Context enricher is used to add additional information to the log context.
 */
interface ContextEnricherInterface
{
    public function process(array $context): array;
}
