<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\Message;

use PHPUnit\Framework\TestCase;
use Yiisoft\Log\Message\ContextValueExtractor;

final class ContextValueExtractorTest extends TestCase
{
    public static function dataExtract(): array
    {
        return [
            'empty-key-found' => [['' => 'found'], '', [true, 'found']],
            'empty-key-not-found' => [['foo' => 'bar'], '', [false, null]],
            'simple-key' => [['foo' => 'bar'], 'foo', [true, 'bar']],
            'missing-key' => [['foo' => 'bar'], 'baz', [false, null]],
            'nested-key' => [['user' => ['name' => 'John']], 'user.name', [true, 'John']],
            'nested-key-not-found' => [['user' => ['name' => 'John']], 'user.age', [false, null]],
            'nested-key-non-array-intermediate' => [['user' => 'string'], 'user.name', [false, null]],
            'escaped-dot' => [['user.name' => 'John'], 'user\.name', [true, 'John']],
            'escaped-backslash' => [['user\\' => 'John'], 'user\\\\', [true, 'John']],
            'deeply-nested' => [['a' => ['b' => ['c' => 'deep']]], 'a.b.c', [true, 'deep']],
            'backslash-key-nested' => [['a\\' => ['b' => 'value']], 'a\\\\.b', [true, 'value']],
            'multiple-backslashes-before-dot' => [['a\\\\' => ['b' => 'value']], 'a\\\\\\\\.b', [true, 'value']],
            'escaped-dot-and-nesting' => [['a.b' => ['c' => 'value']], 'a\\.b.c', [true, 'value']],
        ];
    }

    /**
     * @dataProvider dataExtract
     */
    public function testExtract(array $context, string $key, array $expected): void
    {
        $this->assertSame($expected, ContextValueExtractor::extract($context, $key));
    }
}
