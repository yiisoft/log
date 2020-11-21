<?php

declare(strict_types=1);

namespace Yiisoft\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * MessageGroupInterface provides methods to make it easier to work with log messages.
 */
interface MessageGroupInterface
{
    /**
     * Adds a log message to the group.
     *
     * Each log message is of the following structure:
     *
     * ```
     * [
     *   [0] => level (string)
     *   [1] => message (mixed, can be a string or some complex data, such as an exception object)
     *   [2] => context (array)
     * ]
     * ```
     *
     * Message context has a following keys:
     *
     * - category: string, message category.
     * - time: float, message timestamp obtained by microtime(true).
     * - trace: array, debug backtrace, contains the application code call stacks.
     * - memory: int, memory usage in bytes, obtained by `memory_get_usage()`.
     *
     * @param string $level Log message level.
     * @param mixed $message Log message.
     * @param array $context Log message context.
     * @see LoggerInterface::log()
     */
    public function add(string $level, $message, array $context = []): void;


    /**
     * Adds multiple log messages to the group.
     *
     * @param array $messages The list of log messages.
     * @see MessageGroupInterface::add()
     */
    public function addMultiple(array $messages): void;

    /**
     * Returns all log messages.
     *
     * @return array All log messages.
     */
    public function all(): array;

    /**
     * Removes all log messages.
     */
    public function clear(): void;

    /**
     * Returns the number of log messages in the group.
     *
     * @return int The number of log messages in the group.
     */
    public function count(): int;

    /**
     * Sets the log message levels that current group is interested in.
     *
     * The parameter should be an array of interested level names.
     *
     * Defaults is empty array, meaning all available levels.
     *
     * For example:
     *
     * ```php
     * ['error', 'warning'],
     * // or
     * [LogLevel::ERROR, LogLevel::WARNING]
     * ```
     *
     * @param string[] $levels The log message levels.
     * @see LogLevel See constants for valid level names.
     */
    public function setLevels(array $levels): void;

    /**
     * Gets the log message levels of the current group.
     *
     * @return string[] The log message levels.
     * @see MessageGroupInterface::setLevels()
     */
    public function getLevels(): array;
}
