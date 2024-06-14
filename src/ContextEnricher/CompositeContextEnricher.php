<?php

declare(strict_types=1);

namespace Yiisoft\Log\ContextEnricher;

/**
 * `CompositeContextEnricher` allows to combine multiple context enrichers into one.
 */
final class CompositeContextEnricher implements ContextEnricherInterface
{
    /**
     * @var ContextEnricherInterface[]
     */
    private array $enrichers;

    public function __construct(
        ContextEnricherInterface ...$enrichers
    ) {
        $this->enrichers = $enrichers;
    }

    public function process(array $context): array
    {
        foreach ($this->enrichers as $enricher) {
            $context = $enricher->process($context);
        }
        return $context;
    }
}
