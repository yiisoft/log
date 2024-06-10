<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use Yiisoft\Log\Message;

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

    public function parseMessageProvider(): array
    {
        return [
            'no-placeholder' => [
                'no placeholder',
                ['foo' => 'some'],
                'no placeholder',
            ],
            'string-placeholder' => [
                'has {foo} placeholder',
                ['foo' => 'some'],
                'has some placeholder',
            ],
            'placeholder-wo-context' => [
                'has {foo} placeholder',
                [],
                'has {foo} placeholder',
            ],
        ];
    }

    /**
     * @dataProvider parseMessageProvider
     */
    public function testParseMessage(string $message, array $context, string $expected): void
    {
        $message = new Message(LogLevel::INFO, $message, $context);
        $this->assertSame($expected, $message->message());
    }
}
