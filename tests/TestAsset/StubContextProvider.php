<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\TestAsset;

use Yiisoft\Log\ContextProvider\ContextProviderInterface;

final class StubContextProvider implements ContextProviderInterface
{
    public function __construct(
        private array $context = [],
    ) {}

    public function getContext(): array
    {
        return $this->context;
    }
}
