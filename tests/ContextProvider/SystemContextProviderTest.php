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
        $context = $provider->getContext();

        $this->assertArrayHasKey('time', $context);
        $this->assertArrayHasKey('trace', $context);
        $this->assertArrayHasKey('memory', $context);
        $this->assertArrayHasKey('category', $context);
    }
}
