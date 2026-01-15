<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use stdClass;
use Yiisoft\Log\Message;
use Yiisoft\Log\Tests\TestAsset\StringableObject;

final class MessageTest extends TestCase
{
    public function testGetters(): void
    {
        $message = new Message(LogLevel::INFO, 'message', ['foo' => 'bar']);

        $this->assertSame(LogLevel::INFO, $message->level());
        $this->assertSame('message', $message->message());
        $this->assertSame(['foo' => 'bar'], $message->context());
        $this->assertSame('bar', $message->context('foo'));
        $this->assertNull($message->context('not-exist'));
        $this->assertSame('default', $message->context('not-exist', 'default'));
    }

    public function levelProvider(): array
    {
        return [
            LogLevel::EMERGENCY => [LogLevel::EMERGENCY],
            LogLevel::ALERT => [LogLevel::ALERT],
            LogLevel::CRITICAL => [LogLevel::CRITICAL],
            LogLevel::ERROR => [LogLevel::ERROR],
            LogLevel::WARNING => [LogLevel::WARNING],
            LogLevel::NOTICE => [LogLevel::NOTICE],
            LogLevel::INFO => [LogLevel::INFO],
            LogLevel::DEBUG => [LogLevel::DEBUG],
        ];
    }

    /**
     * @dataProvider levelProvider
     */
    public function testConstructorAndLevel(string $level): void
    {
        $message = new Message($level, 'message');
        $this->assertSame($level, $message->level());
    }

    public function testConstructorThrowExceptionForUnknownLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Message('unknown', 'message');
    }

    public function dataParseMessage(): array
    {
        return [
            'no-placeholder' => [
                'no placeholder',
                ['foo' => 'some'],
                'no placeholder',
            ],
            'string' => [
                'has {foo} placeholder',
                ['foo' => 'some'],
                'has some placeholder',
            ],
            'without-value' => [
                'has {foo} placeholder',
                [],
                'has {foo} placeholder',
            ],
            'null' => [
                'has "{foo}" placeholder',
                ['foo' => null],
                'has "" placeholder',
            ],
            'array' => [
                'has "{foo}" placeholder',
                ['foo' => ['bar' => 7]],
                <<<TEXT
                has "[
                    'bar' => 7
                ]" placeholder
                TEXT,
            ],
            'nested' => [
                'has "{foo.bar}" placeholder',
                ['foo' => ['bar' => 7]],
                'has "7" placeholder',
            ],
            'nested-non-exist' => [
                'has "{foo.bar}" placeholder',
                ['foo' => []],
                'has "{foo.bar}" placeholder',
            ],
            'nested-non-stringable' => [
                'has "{foo.bar}" placeholder',
                ['foo' => new stdClass()],
                'has "{foo.bar}" placeholder',
            ],
            'stringable' => [
                'has "{foo}" placeholder',
                ['foo' => new StringableObject('test')],
                'has "test" placeholder',
            ],
            'nested-placeholder' => [
                'has "{a{b}c}" placeholder',
                ['a{b}c' => 'test'],
                'has "test" placeholder',
            ],
            'deeply-nested-placeholder' => [
                'has "{a{b}c{d{e}}}" placeholder',
                ['a{b}c{d{e}}' => 'test'],
                'has "test" placeholder',
            ],
            'nested-quoted' => [
                'has "{foo\.ba\\\\r}" placeholder',
                ['foo.ba\\r' => 'test'],
                'has "test" placeholder',
            ],
            'nested-extended-1' => [
                'has "{foo\\\.bar}" placeholder',
                ['foo\\' => ['bar' => 'test']],
                'has "test" placeholder',
            ],
            'nested-extended-2' => [
                'has "{foo\\\\\\\\.bar}" placeholder',
                ['foo\\\\' => ['bar' => 'test']],
                'has "test" placeholder',
            ],
            'nested-extended-3' => [
                'has "{foo\\\.}" placeholder',
                ['foo\\' => ['' => 'test']],
                'has "test" placeholder',
            ],
            'nested-extended-4' => [
                'has "{foo\\\\}" placeholder',
                ['foo\\' => 'test'],
                'has "test" placeholder',
            ],
            'nested-extended-5' => [
                'has "{foo\.bar.a}" placeholder',
                ['foo.bar' => ['a' => 'test']],
                'has "test" placeholder',
            ],
            'nested-extended-6' => [
                'has "{key1\..\.key2\..\.key3}" placeholder',
                ['key1.' => ['.key2.' => ['.key3' => 'test']]],
                'has "test" placeholder',
            ],
            'nested-extended-7' => [
                'has "{key1\..\.key2\..\.key3}" placeholder',
                ['key1.' => ['.key2.' => ['.key3' => 'test']]],
                'has "test" placeholder',
            ],
            'empty' => [
                'Value — "{}"',
                ['' => 'test'],
                'Value — "test"',
            ],
            'multiple-placeholders' => [
                'Placeholder 1: {p1} - Placeholder 2: {p2}',
                ['p1' => 'hello', 'p2' => 'world'],
                'Placeholder 1: hello - Placeholder 2: world',
            ],
            'multiple-placeholders-nested' => [
                'Placeholder 1: {a1{a2{a3}}} - Placeholder 2: {b1{b2{b3}}}',
                ['a1{a2{a3}}' => 'hello', 'b1{b2{b3}}' => 'world'],
                'Placeholder 1: hello - Placeholder 2: world',
            ],
        ];
    }

    /**
     * @dataProvider dataParseMessage
     */
    public function testParseMessage(string $message, array $context, string $expected): void
    {
        $message = new Message(LogLevel::INFO, $message, $context);
        $this->assertSame($expected, $message->message());
    }

    public static function dataCategory(): array
    {
        return [
            'without-category' => [Message::DEFAULT_CATEGORY, []],
            'null-category' => [Message::DEFAULT_CATEGORY, ['category' => null]],
            'with-category' => ['test', ['category' => 'test']],
        ];
    }

    /**
     * @dataProvider dataCategory
     */
    public function testCategory(string $expected, array $context): void
    {
        $message = new Message(LogLevel::INFO, 'message', $context);
        $this->assertSame($expected, $message->category());
    }

    public function testInvalidCategoryType(): void
    {
        $message = new Message(LogLevel::INFO, 'message', ['category' => 23.1]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid category value in log context. Expected "string", got "float".');
        $message->category();
    }
}
