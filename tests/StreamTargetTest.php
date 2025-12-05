<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use Psr\Log\LogLevel;
use RuntimeException;
use Yiisoft\Log\Message;
use Yiisoft\Log\StreamTarget;

use function fclose;
use function fopen;

final class StreamTargetTest extends TestCase
{
    public function testExportWithStringStreamIdentifier(): void
    {
        $target = $this->createStreamTarget('php://output');
        $this->exportStreamTarget($target);
        $this->expectOutputString("[info] message-1\n[debug] message-2\n[error] message-3\n");
    }

    public function testExportWithStreamResource(): void
    {
        $target = $this->createStreamTarget(fopen('php://output', 'w'));
        $this->exportStreamTarget($target);
        $this->expectOutputString("[info] message-1\n[debug] message-2\n[error] message-3\n");
    }

    public function testExportWithReopenedStream(): void
    {
        $target = $this->createStreamTarget(fopen('php://output', 'w'));
        $expected = "[info] message-1\n[debug] message-2\n[error] message-3\n";

        $this->exportStreamTarget($target);
        $this->exportStreamTarget($target);

        $this->expectOutputString($expected . $expected);
    }

    public function testExportThrowExceptionForStreamCannotBeOpened(): void
    {
        $target = $this->createStreamTarget('invalid://uri');
        $this->expectException(RuntimeException::class);
        $this->exportStreamTarget($target);
    }

    public function testExportThrowExceptionForClosedStreamResource(): void
    {
        $stream = fopen('php://output', 'w');
        fclose($stream);
        $target = $this->createStreamTarget($stream);
        $this->expectException(InvalidArgumentException::class);
        $this->exportStreamTarget($target);
    }

    public function errorWritingProvider(): array
    {
        return [
            'input-string' => ['php://input'],
            'input-resource' => [fopen('php://input', 'w')],
            'temp-not-writable' => [fopen('php://temp', 'r')],
            'memory-not-writable' => [fopen('php://memory', 'r')],
        ];
    }

    /**
     * @dataProvider errorWritingProvider
     *
     * @param resource|string $stream
     */
    public function testExportThrowExceptionForErrorWritingToStream($stream): void
    {
        $target = $this->createStreamTarget($stream);
        $this->expectException(RuntimeException::class);
        $this->exportStreamTarget($target);
    }

    /**
     * @param resource|string $stream
     */
    private function createStreamTarget($stream): StreamTarget
    {
        $target = new StreamTarget($stream);
        $target->setFormat(static fn (Message $message) => "[{$message->level()}] {$message->message()}");
        return $target;
    }

    private function exportStreamTarget(StreamTarget $target): void
    {
        $target->collect(
            [
                new Message(LogLevel::INFO, 'message-1', ['foo' => 'bar']),
                new Message(LogLevel::DEBUG, 'message-2', ['foo' => true]),
                new Message(LogLevel::ERROR, 'message-3', ['foo' => 1]),
            ],
            true
        );
    }

    public function testSetLevelsViaConstructor(): void
    {
        $target = new StreamTarget('php://output', [LogLevel::ERROR, LogLevel::INFO]);
        $target->setFormat(static fn (Message $message) => "[{$message->level()}] {$message->message()}");

        $target->collect(
            [
                new Message(LogLevel::INFO, 'message-1', ['foo' => 'bar']),
                new Message(LogLevel::DEBUG, 'message-2', ['foo' => true]),
                new Message(LogLevel::ERROR, 'message-3', ['foo' => 1]),
            ],
            true
        );

        $this->expectOutputString("[info] message-1\n[error] message-3\n");
    }
}
