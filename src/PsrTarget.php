<?php

declare(strict_types=1);

namespace Yiisoft\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * PsrTarget is a log target which simply passes messages to another PSR-3 compatible logger.
 */
final class PsrTarget extends Target
{
    /**
     * Sets the PSR-3 logger used to save messages of this target.
     *
     * @param LoggerInterface $logger The logger instance to be used for messages processing.
     * @param string[] $levels The {@see LogLevel log message levels} that this target is interested in.
     * @param string|callable|null $contextFormat A context format for the log context output. See {@see Target::__construct()}.
     * @param callable|null $stringConverter A PHP callable that converts a context value to a string. See {@see Target::__construct()}.
     */
    public function __construct(
        private LoggerInterface $logger,
        array $levels = [],
        string|callable|null $contextFormat = null,
        ?callable $stringConverter = null,
    ) {
        parent::__construct($levels, $contextFormat, $stringConverter);
    }

    /**
     * @return LoggerInterface The logger instance.
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    protected function export(): void
    {
        foreach ($this->getMessages() as $message) {
            /** @var array $context */
            $context = $message->context();
            $this->logger->log($message->level(), $message->message(), $context);
        }
    }
}
