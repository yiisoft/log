<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use stdClass;
use Yiisoft\Log\Message;
use Yiisoft\Log\PsrTarget;

use function json_encode;

final class PsrTargetTest extends TestCase
{
    private PsrTarget $target;

    public function setUp(): void
    {
        $this->target = new PsrTarget(new class () implements LoggerInterface {
            use LoggerTrait;

            public string $message = '';

            public function log($level, $message, array $context = []): void
            {
                echo "{$level}: {$message}: " . json_encode($context, JSON_THROW_ON_ERROR);
            }
        });
    }

    public function messageProvider(): array
    {
        return [
            LogLevel::EMERGENCY => [LogLevel::EMERGENCY, 'emergency message', []],
            LogLevel::ALERT => [LogLevel::ALERT, 'alert message', ['foo' => 'bar']],
            LogLevel::CRITICAL => [LogLevel::CRITICAL, 'critical message', ['foo' => 1]],
            LogLevel::ERROR => [LogLevel::ERROR, 'error message', ['foo' => 1.1]],
            LogLevel::WARNING => [LogLevel::WARNING, 'warning message', ['foo' => 1.1]],
            LogLevel::NOTICE => [LogLevel::NOTICE, 'notice message', ['foo' => true]],
            LogLevel::INFO => [LogLevel::INFO, 'info message', ['foo' => ['bar' => 'baz']]],
            LogLevel::DEBUG => [LogLevel::DEBUG, 'debug message', ['foo' => new stdClass()]],
        ];
    }

    /**
     * @dataProvider messageProvider
     */
    public function testPsrLogInterfaceMethods(string $level, string $message, array $context): void
    {
        $this->assertInstanceOf(LoggerInterface::class, $this->target->getLogger());

        $this->target->collect([new Message($level, $message, $context)], true);
        $this->expectOutputString("{$level}: {$message}: " . json_encode($context, JSON_THROW_ON_ERROR));
    }

    public function testSetLevelsViaConstructor(): void
    {
        $target = new PsrTarget(
            new class () implements LoggerInterface {
                use LoggerTrait;

                public function log($level, $message, array $context = []): void
                {
                    echo "{$level}: {$message}";
                }
            },
            [LogLevel::ERROR, LogLevel::INFO]
        );

        $target->collect(
            [
                new Message(LogLevel::INFO, 'message-1', ['foo' => 'bar']),
                new Message(LogLevel::DEBUG, 'message-2', ['foo' => true]),
                new Message(LogLevel::ERROR, 'message-3', ['foo' => 1]),
            ],
            true
        );

        $this->expectOutputString("info: message-1error: message-3");
    }
}
