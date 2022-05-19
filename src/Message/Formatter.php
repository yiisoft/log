<?php

declare(strict_types=1);

namespace Yiisoft\Log\Message;

use DateTime;
use RuntimeException;
use Yiisoft\Log\Message;
use Yiisoft\VarDumper\VarDumper;

use function gettype;
use function implode;
use function is_string;
use function is_object;
use function method_exists;
use function microtime;
use function sprintf;

/**
 * Formatter formats log messages.
 *
 * @internal
 */
final class Formatter
{
    /**
     * @var callable|null PHP callable that returns a string representation of the log message.
     *
     * If not set, {@see Formatter::defaultFormat()} will be used.
     *
     * The signature of the callable should be `function (Message $message, array $commonContext): string;`.
     */
    private $format;

    /**
     * @var callable|null PHP callable that returns a string to be prefixed to every exported message.
     *
     * If not set, {@see Formatter::getPrefix()} will be used, which prefixes
     * the message with context information such as user IP, user ID and session ID.
     *
     * The signature of the callable should be `function (Message $message, array $commonContext): string;`.
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
     * @see Formatter::$format
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
     * @see Formatter::$prefix
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
     * @see Formatter::$timestampFormat
     */
    public function setTimestampFormat(string $timestampFormat): void
    {
        $this->timestampFormat = $timestampFormat;
    }

    /**
     * Formats a log message for display as a string.
     *
     * @param Message $message The log message to be formatted.
     * @param array $commonContext The user parameters in the `key => value` format.
     *
     * @throws RuntimeException for a callable "format" that does not return a string.
     *
     * @return string The formatted log message.
     */
    public function format(Message $message, array $commonContext): string
    {
        if ($this->format === null) {
            return $this->defaultFormat($message, $commonContext);
        }

        $formatted = ($this->format)($message, $commonContext);

        if (!is_string($formatted)) {
            throw new RuntimeException(sprintf(
                'The PHP callable "format" must return a string, %s received.',
                gettype($formatted)
            ));
        }

        return $this->getPrefix($message, $commonContext) . $formatted;
    }

    /**
     * Default formats a log message for display as a string.
     *
     * @param Message $message The log message to be default formatted.
     * @param array $commonContext The user parameters in the `key => value` format.
     *
     * @return string The default formatted log message.
     */
    private function defaultFormat(Message $message, array $commonContext): string
    {
        $time = $this->getTime($message);
        $prefix = $this->getPrefix($message, $commonContext);
        $context = $this->getContext($message, $commonContext);
        $category = $message->context('category', CategoryFilter::DEFAULT);

        return "{$time} {$prefix}[{$message->level()}][{$category}] {$message->message()}{$context}";
    }

    /**
     * Gets formatted timestamp for message, according to {@see Formatter::$timestampFormat}.
     *
     * @param Message $message The log message.
     *
     * @return string Formatted timestamp for message.
     */
    private function getTime(Message $message): string
    {
        $timestamp = (string) $message->context('time', microtime(true));

        $format = match (true) {
            str_contains($timestamp, '.') => 'U.u',
            str_contains($timestamp, ',') => 'U,u',
            default => 'U',
        };

        return DateTime::createFromFormat($format, $timestamp)->format($this->timestampFormat);
    }

    /**
     * Gets a string to be prefixed to the given message.
     *
     * If {@see Formatter::$prefix} is configured it will return the result of the callback.
     * The default implementation will return user IP, user ID and session ID as a prefix.
     *
     * @param Message $message The log message being exported.
     * @param array $commonContext The user parameters in the `key => value` format.
     *
     * @throws RuntimeException for a callable "prefix" that does not return a string.
     *
     * @return string The log prefix string.
     */
    private function getPrefix(Message $message, array $commonContext): string
    {
        if ($this->prefix === null) {
            return '';
        }

        $prefix = ($this->prefix)($message, $commonContext);

        if (!is_string($prefix)) {
            throw new RuntimeException(sprintf(
                'The PHP callable "prefix" must return a string, %s received.',
                gettype($prefix)
            ));
        }

        return $prefix;
    }

    /**
     * Gets the context information to be logged.
     *
     * @param Message $message The log message.
     * @param array $commonContext The user parameters in the `key => value` format.
     *
     * @return string The context information. If an empty string, it means no context information.
     */
    private function getContext(Message $message, array $commonContext): string
    {
        $trace = $this->getTrace($message);
        $context = [];
        $common = [];

        if ($trace !== '') {
            $context[] = $trace;
        }

        foreach ($message->context() as $name => $value) {
            if ($name !== 'trace') {
                $context[] = "{$name}: " . $this->convertToString($value);
            }
        }

        foreach ($commonContext as $name => $value) {
            $common[] = "{$name}: " . $this->convertToString($value);
        }

        return (empty($context) ? '' : "\n\nMessage context:\n\n" . implode("\n", $context))
            . (empty($common) ? '' : "\n\nCommon context:\n\n" . implode("\n", $common)) . "\n";
    }

    /**
     * Gets debug backtrace in string representation.
     *
     * @param Message $message The log message.
     *
     * @return string Debug backtrace in string representation.
     */
    private function getTrace(Message $message): string
    {
        $traces = (array) $message->context('trace', []);

        foreach ($traces as $key => $trace) {
            if (isset($trace['file'], $trace['line'])) {
                $traces[$key] = "in {$trace['file']}:{$trace['line']}";
            }
        }

        return empty($traces) ? '' : "trace:\n    " . implode("\n    ", $traces);
    }

    /**
     * Converts a value to a string.
     *
     * @param mixed $value The value to convert
     *
     * @return string Converted string.
     */
    private function convertToString(mixed $value): string
    {
        if (is_object($value) && method_exists($value, '__toString')) {
            return $value->__toString();
        }

        return VarDumper::create($value)->asString();
    }
}
