<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\ContextProvider;

use PHPUnit\Framework\TestCase;
use Yiisoft\Log\ContextProvider\CommonContextProvider;

final class CommonContextProviderTest extends TestCase
{
    public function testBase(): void
    {
        $data = ['key' => 'value'];

        $provider = new CommonContextProvider($data);

        $this->assertSame($data, $provider->getContext());
    }
}
