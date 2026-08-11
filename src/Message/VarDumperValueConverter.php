<?php

declare(strict_types=1);

namespace Yiisoft\Log\Message;

use Yiisoft\VarDumper\VarDumper;

use function is_object;
use function method_exists;

/**
 * Default converter of a context value to its string representation.
 *
 * Returns the result of `__toString()` for stringable objects and falls back to {@see VarDumper} otherwise.
 *
 * @internal
 */
final class VarDumperValueConverter
{
    public function __invoke(mixed $value): string
    {
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return VarDumper::create($value)->asString();
    }
}
