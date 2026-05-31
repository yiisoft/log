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
     * @param string[] $categories The log message categories that this target is interested in.
     * @param string[] $except The log message categories that this target is NOT interested in.
     * @param callable|null $format A PHP callable that returns a string representation of the log message.
     * @param callable|null $prefix A PHP callable that returns a string to be prefixed to every exported message.
     * @param string|null $timestampFormat The date format for the log timestamp.
     * @param int|null $exportInterval How many messages should be accumulated before they are exported.
     * @param bool|callable $enabled Whether this target is enabled, or a PHP callable that returns a boolean.
     */
    public function __construct(
        private LoggerInterface $logger,
        array $levels = [],
        array $categories = [],
        array $except = [],
        ?callable $format = null,
        ?callable $prefix = null,
        ?string $timestampFormat = null,
        ?int $exportInterval = null,
        bool|callable $enabled = true,
    ) {
        parent::__construct($levels, $categories, $except, $format, $prefix, $timestampFormat, $exportInterval, $enabled);
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
