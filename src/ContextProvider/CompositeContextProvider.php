<?php

declare(strict_types=1);

namespace Yiisoft\Log\ContextProvider;

/**
 * `CompositeContextProvider` allows to combine multiple context providers into one.
 */
final class CompositeContextProvider implements ContextProviderInterface
{
    /**
     * @var ContextProviderInterface[]
     */
    private array $providers;

    public function __construct(
        ContextProviderInterface ...$providers
    ) {
        $this->providers = $providers;
    }

    public function getContext(): array
    {
        $context = [];
        foreach ($this->providers as $provider) {
            $context = array_merge($context, $provider->getContext());
        }
        return $context;
    }
}
