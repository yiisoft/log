<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\ContextProvider;

use PHPUnit\Framework\TestCase;
use Yiisoft\Log\ContextProvider\CompositeContextProvider;
use Yiisoft\Log\Tests\TestAsset\StubContextProvider;

final class CompositeContextProviderTest extends TestCase
{
    public function testBase(): void
    {
        $provider1 = new StubContextProvider([
            'a' => 1,
            'b' => 2,
        ]);
        $provider2 = new StubContextProvider([
            'b' => 3,
            'c' => 4,
        ]);

        $compositeProvider = new CompositeContextProvider($provider1, $provider2);

        $context = $compositeProvider->getContext();

        $this->assertSame(
            [
                'a' => 1,
                'b' => 3,
                'c' => 4,
            ],
            $context,
        );
    }
}
