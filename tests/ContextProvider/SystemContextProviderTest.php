<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\ContextProvider;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Log\ContextProvider\SystemContextProvider;

final class SystemContextProviderTest extends TestCase
{
    public function testWrongTraceLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SystemContextProvider(-1);
    }

    public function testContextHasNeededData(): void
    {
        $provider = new SystemContextProvider();

        $this->assertArrayHasKey('time', $provider->getContext());
        $this->assertArrayHasKey('trace', $provider->getContext());
        $this->assertArrayHasKey('memory', $provider->getContext());
        $this->assertArrayHasKey('category', $provider->getContext());
    }
}
