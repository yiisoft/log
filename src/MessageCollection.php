<?php

declare(strict_types=1);

namespace Yiisoft\Log;

use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use Yiisoft\VarDumper\VarDumper;

use function count;
use function is_array;
use function is_scalar;
use function method_exists;
use function preg_replace_callback;

/**
 * MessageGroupInterface provides methods to make it easier to work with log messages.
 */
final class MessageCollection
{
    /**
     * @var string[] The log message levels that this collection is interested in.
     * @see LogLevel See constants for valid level names.
     *
     * The value should be an array of interested level names.
     *
     * For example:
     *
     * ```php
     * ['error', 'warning'],
     * // or
     * [LogLevel::ERROR, LogLevel::WARNING]
     * ```
     *
     * Defaults is empty array, meaning all available levels.
     */
    private array $levels = [];

    /**
     * @var array[] The log messages.
     *
     ** Each log message is of the following structure:
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
     */
    private array $messages = [];

    /**
     * Adds a log message to the collection.
     *
     * @param mixed $level Log message level.
     * @param mixed $message Log message.
     * @param array $context Log message context.
     * @throws InvalidArgumentException for invalid log message level.
     * @see MessageCollection::$messages
     * @see LoggerTrait::log()
     */
    public function add($level, $message, array $context = []): void
    {
        $this->messages[] = [Logger::getLevelName($level), $this->parse($this->prepare($message), $context), $context];
    }

    /**
     * Adds multiple log messages to the collection.
     *
     * @param array $messages The list of log messages.
     * @throws InvalidArgumentException for invalid message structure.
     * @see MessageCollection::$messages
     */
    public function addMultiple(array $messages): void
    {
        foreach ($messages as $message) {
            $this->checkStructure($message);
            $this->add($message[0], $message[1], $message[2]);
        }
    }

    /**
     * Returns all log messages.
     *
     * @return array All log messages.
     * @see MessageCollection::$messages
     */
    public function all(): array
    {
        return $this->messages;
    }

    /**
     * Removes all log messages.
     */
    public function clear(): void
    {
        $this->messages = [];
    }

    /**
     * Returns the number of log messages in the collection.
     *
     * @return int The number of log messages in the collection.
     */
    public function count(): int
    {
        return count($this->messages);
    }

    /**
     * Checks log message structure.
     *
     * @param mixed $message The log message to be checked.
     * @throws InvalidArgumentException for invalid message structure.
     * @see MessageCollection::$messages
     */
    public function checkStructure($message): void
    {
        if (!is_array($message) || !isset($message[0], $message[1], $message[2]) || !is_array($message[2])) {
            throw new InvalidArgumentException('The message structure is not valid.');
        }
    }

    /**
     * Sets the log message levels that current collection is interested in.
     *
     * @param string[] $levels The log message levels.
     * @throws InvalidArgumentException for invalid log message level.
     * @see MessageCollection::$levels
     */
    public function setLevels(array $levels): void
    {
        foreach ($levels as $key => $level) {
            $levels[$key] = Logger::getLevelName($level);
        }

        $this->levels = $levels;
    }

    /**
     * Gets the log message levels of the current group.
     *
     * @return string[] The log message levels.
     * @see MessageCollection::$levels
     */
    public function getLevels(): array
    {
        return $this->levels;
    }

    /**
     * Prepares log message for logging.
     *
     * @param mixed $message Raw log message.
     * @return string Prepared log message.
     */
    private function prepare($message): string
    {
        if (is_scalar($message) || method_exists($message, '__toString')) {
            return (string) $message;
        }

        return VarDumper::create($message)->export();
    }

    /**
     * Parses log message resolving placeholders in the form: "{foo}",
     * where foo will be replaced by the context data in key "foo".
     *
     * @param string $message Log message.
     * @param array $context Message context.
     * @return string Parsed message.
     */
    private function parse(string $message, array $context): string
    {
        return preg_replace_callback('/{([\w.]+)}/', static function (array $matches) use ($context) {
            $placeholderName = $matches[1];

            if (isset($context[$placeholderName])) {
                return (string) $context[$placeholderName];
            }

            return $matches[0];
        }, $message);
    }
}
