<?php

declare(strict_types=1);

namespace Yiisoft\Log;

use Psr\Log\LoggerInterface;

/**
 * PsrTarget is a log target which simply passes messages to another PSR-3 compatible logger,
 * which is specified via constructor.
 */
class PsrTarget extends Target
{
    /**
     * @var LoggerInterface logger instance to be used for messages processing.
     */
    private LoggerInterface $logger;

    /**
     * Sets the PSR-3 logger used to save messages of this target.
     * @param LoggerInterface $logger logger instance.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface logger instance.
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function export(): void
    {
        foreach ($this->getMessages() as $message) {
            [$level, $text, $context] = $message;
            $this->getLogger()->log($level, $text, $context);
        }
    }
}
