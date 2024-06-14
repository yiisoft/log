<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\ContextEnricher;

use PHPUnit\Framework\TestCase;
use Yiisoft\Log\ContextEnricher\CompositeContextEnricher;
use Yiisoft\Log\ContextEnricher\ContextEnricherInterface;

final class CompositeContextEnricherTest extends TestCase
{
    public function testBase(): void
    {
        $enricher1 = new class () implements ContextEnricherInterface {
            public function process(array $context): array
            {
                $context['a'] = 1;
                return $context;
            }
        };
        $enricher2 = new class () implements ContextEnricherInterface {
            public function process(array $context): array
            {
                $context['b'] = 2;
                return $context;
            }
        };

        $compositeEnricher = new CompositeContextEnricher($enricher1, $enricher2);

        $context = $compositeEnricher->process(['key' => 'value']);

        $this->assertSame(
            [
                'key' => 'value',
                'a' => 1,
                'b' => 2,
            ],
            $context
        );
    }
}
