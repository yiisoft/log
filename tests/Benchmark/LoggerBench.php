<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\Benchmark;

use Psr\Log\LogLevel;
use Yiisoft\Log\Logger;
use Yiisoft\Log\Target;

final class LoggerBench
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = new Logger([new class extends Target {
            protected function export(): void
            {
                // noop
            }
        }]);
    }

    public function benchLogSimple(): void
    {
        $this->logger->log(LogLevel::INFO, 'simple message');
    }

    public function benchLogWithPlaceholder(): void
    {
        $this->logger->log(LogLevel::INFO, 'has {foo} placeholder', ['foo' => 'some']);
    }

    public function benchLogWithMultiplePlaceholders(): void
    {
        $this->logger->log(
            LogLevel::INFO,
            'Placeholder 1: {p1} - Placeholder 2: {p2}',
            ['p1' => 'hello', 'p2' => 'world'],
        );
    }
}
