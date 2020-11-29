<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use stdClass;
use Yiisoft\Log\PsrTarget;

use function json_encode;

class PsrTargetTest extends TestCase
{
    private PsrTarget $target;

    public function setUp(): void
    {
        $this->target = new PsrTarget(new class() implements LoggerInterface {
            use LoggerTrait;

            public string $message = '';

            public function log($level, $message, array $context = []): void
            {
                echo "{$level}: {$message}: " . json_encode($context);
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
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function testPsrLogInterfaceMethods(string $level, string $message, array $context): void
    {
        $this->assertInstanceOf(LoggerInterface::class, $this->target->getLogger());

        $this->target->setLogVars([]);
        $this->target->collect([[$level, $message, $context]], true);
        $this->expectOutputString("{$level}: {$message}: " . json_encode($context));
    }
}
