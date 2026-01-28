<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\Benchmark;

use Psr\Log\LogLevel;
use Yiisoft\Log\Message;

final class MessageBench
{
    public function benchNoPlaceholder(): void
    {
        new Message(LogLevel::INFO, 'no placeholder', ['foo' => 'some']);
    }

    public function benchSinglePlaceholderScalar(): void
    {
        new Message(LogLevel::INFO, 'has {foo} placeholder', ['foo' => 'some']);
    }

    public function benchPlaceholderMissing(): void
    {
        new Message(LogLevel::INFO, 'has {foo} placeholder', []);
    }

    public function benchPlaceholderNull(): void
    {
        new Message(LogLevel::INFO, 'has "{foo}" placeholder', ['foo' => null]);
    }

    public function benchPlaceholderArray(): void
    {
        new Message(LogLevel::INFO, 'has "{foo}" placeholder', ['foo' => ['bar' => 7]]);
    }

    public function benchNestedPlaceholder(): void
    {
        new Message(LogLevel::INFO, 'has "{foo.bar}" placeholder', ['foo' => ['bar' => 7]]);
    }

    public function benchDeeplyNestedPlaceholder(): void
    {
        new Message(LogLevel::INFO, 'has "{foo.bar.baz}" placeholder', ['foo' => ['bar' => ['baz' => 7]]]);
    }

    public function benchMultiplePlaceholders(): void
    {
        new Message(
            LogLevel::INFO,
            'Placeholder 1: {p1} - Placeholder 2: {p2}',
            ['p1' => 'hello', 'p2' => 'world'],
        );
    }
}
