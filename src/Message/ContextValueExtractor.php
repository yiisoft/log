<?php

declare(strict_types=1);

namespace Yiisoft\Log\Message;

/**
 * @internal
 */
final class ContextValueExtractor
{
    public static function extract(array $context, string $key, mixed $default): mixed
    {
        $path = self::parsePath($key);

        $lastKey = array_pop($path);
        $array = $context;
        foreach ($path as $pathItem) {
            $array = array_key_exists($pathItem, $array) ? $array[$pathItem] : null;
            if (!is_array($array)) {
                return $default;
            }
        }

        return array_key_exists($lastKey, $array) ? $array[$lastKey] : $default;
    }

    /**
     * @psalm-return list<string>
     */
    private static function parsePath(string $path): array
    {
        if ($path === '') {
            return [];
        }

        if (!str_contains($path, '.')) {
            return [str_replace('\\\\', '\\', $path)];
        }

        /** @psalm-var non-empty-list<array{0:string, 1:int}> $matches */
        $matches = preg_split(
            sprintf(
                '/(?<!%1$s)((?>%1$s%1$s)*)%2$s/',
                preg_quote('\\', '/'),
                preg_quote('.', '/')
            ),
            $path,
            -1,
            PREG_SPLIT_OFFSET_CAPTURE
        );
        $result = [];
        $countResults = count($matches);
        for ($i = 1; $i < $countResults; $i++) {
            $l = $matches[$i][1] - $matches[$i - 1][1] - strlen($matches[$i - 1][0]) - 1;
            $result[] = $matches[$i - 1][0] . ($l > 0 ? str_repeat('\\', $l) : '');
        }
        $result[] = $matches[$countResults - 1][0];

        return array_map(
            static fn(string $key): string => str_replace(
                [
                    '\\\\',
                    '\\.',
                ],
                [
                    '\\',
                    '.',
                ],
                $key
            ),
            $result
        );
    }
}
