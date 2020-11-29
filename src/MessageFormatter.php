<?php

declare(strict_types=1);

namespace Yiisoft\Log;

use DateTime;
use RuntimeException;

use function gettype;
use function implode;
use function is_array;
use function is_string;
use function microtime;
use function sprintf;
use function strpos;

final class MessageFormatter
{
    /**
     * @var callable|null PHP callable that returns a string representation of the log message.
     *
     * If not set, {@see MessageFormatter::defaultFormat()} will be used.
     *
     * The signature of the callable should be `function (array $message): string;`.
     */
    private $format;

    /**
     * @var callable|null PHP callable that returns a string to be prefixed to every exported message.
     *
     * If not set, {@see MessageFormatter::getPrefix()} will be used, which prefixes
     * the message with context information such as user IP, user ID and session ID.
     *
     * The signature of the callable should be `function (array $message): string;`.
     */
    private $prefix;

    /**
     * @var string The date format for the log timestamp. Defaults to `Y-m-d H:i:s.u`.
     */
    private string $timestampFormat = 'Y-m-d H:i:s.u';

    /**
     * Sets the format for the string representation of the log message.
     *
     * @param callable $format The PHP callable to get a string representation of the log message.
     *
     * @see MessageFormatter::$format
     */
    public function setFormat(callable $format): void
    {
        $this->format = $format;
    }

    /**
     * Sets a PHP callable that returns a string to be prefixed to every exported message.
     *
     * @param callable $prefix The PHP callable to get a string prefix of the log message.
     *
     * @see MessageFormatter::$prefix
     */
    public function setPrefix(callable $prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * Sets a date format for the log timestamp.
     *
     * @param string $timestampFormat The date format for the log timestamp.
     *
     * @see MessageFormatter::$timestampFormat
     */
    public function setTimestampFormat(string $timestampFormat): void
    {
        $this->timestampFormat = $timestampFormat;
    }

    /**
     * Formats a log message for display as a string.
     *
     * The message structure follows that in {@see MessageCollection::$messages}.
     *
     * @param array $message The log message to be formatted.
     *
     * @throws RuntimeException for a callable "format" that does not return a string.
     *
     * @return string The formatted log message.
     */
    public function format(array $message): string
    {
        if ($this->format === null) {
            return $this->defaultFormat($message);
        }

        $formatted = ($this->format)($message);

        if (!is_string($formatted)) {
            throw new RuntimeException(sprintf(
                'The PHP callable "format" must return a string, %s received.',
                gettype($formatted)
            ));
        }

        return $this->getPrefix($message) . $formatted;
    }

    /**
     * Default formats a log message for display as a string.
     *
     * The message structure follows that in {@see MessageCollection::$messages}.
     *
     * @param array $message The log message to be default formatted.
     *
     * @return string The default formatted log message.
     */
    private function defaultFormat(array $message): string
    {
        [$level, $text, $context] = $message;
        $timestamp = $context['time'] ?? microtime(true);
        $category = $context['category'] ?? MessageCategoryFilter::DEFAULT;

        $traces = [];
        if (isset($context['trace']) && is_array($context['trace'])) {
            foreach ($context['trace'] as $trace) {
                if (isset($trace['file'], $trace['line'])) {
                    $traces[] = "in {$trace['file']}:{$trace['line']}";
                }
            }
        }

        return $this->getTime($timestamp) . " {$this->getPrefix($message)}[{$level}][{$category}] {$text}"
            . (empty($traces) ? '' : "\n    " . implode("\n    ", $traces));
    }

    /**
     * Gets a string to be prefixed to the given message.
     *
     * If {@see MessageFormatter::$prefix} is configured it will return the result of the callback.
     * The default implementation will return user IP, user ID and session ID as a prefix.
     * The message structure follows that in {@see MessageCollection::$messages}.
     *
     * @param array $message The log message being exported.
     *
     * @throws RuntimeException for a callable "prefix" that does not return a string.
     *
     * @return string The log prefix string.
     */
    private function getPrefix(array $message): string
    {
        if ($this->prefix === null) {
            return '';
        }

        $prefix = ($this->prefix)($message);

        if (!is_string($prefix)) {
            throw new RuntimeException(sprintf(
                'The PHP callable "prefix" must return a string, %s received.',
                gettype($prefix)
            ));
        }

        return $prefix;
    }

    /**
     * Gets formatted timestamp for message, according to {@see MessageFormatter::$timestampFormat}.
     *
     * @param float|int $timestamp The timestamp to be formatted.
     *
     * @return string Formatted timestamp for message.
     */
    private function getTime($timestamp): string
    {
        $timestamp = (string) $timestamp;
        $format = strpos($timestamp, '.') === false ? 'U' : 'U.u';
        return DateTime::createFromFormat($format, $timestamp)->format($this->timestampFormat);
    }
}
