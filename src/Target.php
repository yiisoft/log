<?php

declare(strict_types=1);

namespace Yiisoft\Log;

use DateTime;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\VarDumper\VarDumper;

use function gettype;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_callable;
use function microtime;
use function sprintf;
use function strpos;

abstract class Target
{
    private MessageCategory $categories;
    private MessageGroupInterface $messages;

    /**
     * @var string[] list of the PHP predefined variables that should be logged in a message.
     *
     * Note that a variable must be accessible via `$GLOBALS`. Otherwise it won't be logged.
     *
     * Defaults to `['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER']`.
     *
     * Each element can also be specified as one of the following:
     *
     * - `var` - `var` will be logged.
     * - `var.key` - only `var[key]` key will be logged.
     * - `!var.key` - `var[key]` key will be excluded.
     *
     * Note that if you need $_SESSION to logged regardless if session
     * was used you have to open it right at he start of your request.
     *
     * @see \Yiisoft\Arrays\ArrayHelper::filter()
     */
    private array $logVars = ['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER'];

    /**
     * @var callable|null PHP callable that returns a string to be prefixed to every exported message.
     *
     * If not set, {@see Target::getMessagePrefix()} will be used, which prefixes
     * the message with context information such as user IP, user ID and session ID.
     *
     * The signature of the callable should be `function ($message)`.
     */
    private $prefix;

    /**
     * @var int How many log messages should be accumulated before they are exported.
     *
     * Defaults to 1000. Note that messages will always be exported when the application terminates.
     * Set this property to be 0 if you don't want to export messages until the application terminates.
     */
    private int $exportInterval = 1000;

    /**
     * @var string The date format for the log timestamp. Defaults to `Y-m-d H:i:s.u`.
     */
    private string $timestampFormat = 'Y-m-d H:i:s.u';

    /**
     * @var bool|callable Enables or disables the current target to export.
     */
    private $enabled = true;

    /**
     * Exports log messages to a specific destination.
     * Child classes must implement this method.
     */
    abstract public function export(): void;

    /**
     * @param MessageGroupInterface|null $messages If `null`, {@see \Yiisoft\Log\MessageGroup} instance will be used.
     */
    public function __construct(MessageGroupInterface $messages = null)
    {
        $this->categories = new MessageCategory();
        $this->messages = $messages ?? new MessageGroup();
    }

    /**
     * Processes the given log messages.
     *
     * This method will filter the given messages with levels and categories.
     * The message structure follows that in {@see MessageGroupInterface::add()}.
     * And if requested, it will also export the filtering result to specific medium (e.g. email).
     *
     * @param array $messages Log messages to be processed.
     * @param bool $final Whether this method is called at the end of the current application.
     */
    public function collect(array $messages, bool $final): void
    {
        $this->messages->addMultiple($this->filterMessages($messages));
        $count = $this->messages->count();

        if ($count > 0 && ($final || ($this->exportInterval > 0 && $count >= $this->exportInterval))) {
            if (($contextMessage = $this->getContextMessage()) !== '') {
                $this->messages->add(LogLevel::INFO, $contextMessage, [
                    'category' => MessageCategory::DEFAULT,
                    'time' => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)
                ]);
            }
            // set exportInterval to 0 to avoid triggering export again while exporting
            $oldExportInterval = $this->exportInterval;
            $this->exportInterval = 0;
            $this->export();
            $this->exportInterval = $oldExportInterval;
            $this->messages->clear();
        }
    }

    /**
     * Sets a list of log message categories that this target is interested in.
     *
     * @param array $categories The list of log message categories.
     * @return self
     */
    public function setCategories(array $categories): self
    {
        $this->categories->setAllowed($categories);
        return $this;
    }

    /**
     * Gets a list of log message categories that this target is interested in.
     *
     * @return array The list of log message categories.
     */
    public function getCategories(): array
    {
        return $this->categories->getAllowed();
    }

    /**
     * Sets a list of log message categories that this target is NOT interested in.
     *
     * @param array $except The list of log message categories.
     * @return self
     */
    public function setExcept(array $except): self
    {
        $this->categories->setExcepted($except);
        return $this;
    }

    /**
     * Gets a list of log message categories that this target is NOT interested in.
     *
     * @return array The list of excluded categories of log messages.
     */
    public function getExcept(): array
    {
        return $this->categories->getExcepted();
    }

    /**
     * Sets a list of log messages that are retrieved from the logger so far by this log target.
     *
     * @param array $messages The list of log messages.
     * @return self
     */
    public function setMessages(array $messages): self
    {
        $this->messages->addMultiple($messages);
        return $this;
    }

    /**
     * Gets a list of log messages that are retrieved from the logger so far by this log target.
     *
     * @return array The list of log messages.
     */
    public function getMessages(): array
    {
        return $this->messages->all();
    }

    /**
     * Sets a list of log message levels that current target is interested in.
     *
     * @param array $levels The list of log message levels.
     * @return self
     */
    public function setLevels(array $levels): self
    {
        $this->messages->setLevels($levels);
        return $this;
    }

    /**
     * Gets a list of log message levels that current target is interested in.
     *
     * @return array The list of log message levels.
     */
    public function getLevels(): array
    {
        return $this->messages->getLevels();
    }

    /**
     * Sets a list of the PHP predefined variables that should be logged in a message.
     *
     * @param string[] $logVars The list of PHP predefined variables.
     * @return self
     * @throws InvalidArgumentException for non-string values.
     */
    public function setLogVars(array $logVars): self
    {
        foreach ($logVars as $logVar) {
            if (!is_string($logVar)) {
                throw new InvalidArgumentException(sprintf(
                    "The PHP predefined variable must be a string, %s received.",
                    gettype($logVar)
                ));
            }
        }

        $this->logVars = $logVars;
        return $this;
    }

    /**
     * Gets a list of the PHP predefined variables that should be logged in a message.
     *
     * @return string[] The list of the PHP predefined variables.
     */
    public function getLogVars(): array
    {
        return $this->logVars;
    }

    /**
     * Sets a PHP callable that returns a string to be prefixed to every exported message.
     *
     * @param callable $prefix The PHP callable to get a string value from.
     * @return self
     */
    public function setPrefix(callable $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * Gets a PHP callable that returns a string to be prefixed to every exported message.
     *
     * @return callable|null The PHP callable to get a string value from or null.
     */
    public function getPrefix(): ?callable
    {
        return $this->prefix;
    }

    /**
     * Sets how many messages should be accumulated before they are exported.
     *
     * @param int $exportInterval The number of log messages to accumulate before exporting.
     * @return self
     */
    public function setExportInterval(int $exportInterval): self
    {
        $this->exportInterval = $exportInterval;
        return $this;
    }

    /**
     * Gets how many messages should be accumulated before they are exported.
     *
     * @return int The number of messages to accumulate before exporting.
     */
    public function getExportInterval(): int
    {
        return $this->exportInterval;
    }

    /**
     * Sets a date format for the log timestamp.
     *
     * @param string $format The date format for the log timestamp.
     * @return self
     */
    public function setTimestampFormat(string $format): self
    {
        $this->timestampFormat = $format;
        return $this;
    }

    /**
     * Gets a date format for the log timestamp.
     *
     * @return string The date format for the log timestamp.
     */
    public function getTimestampFormat(): string
    {
        return $this->timestampFormat;
    }

    /**
     * Sets a value indicating whether this log target is enabled.
     *
     * A callable may be used to determine whether the log target should be enabled in a dynamic way.
     *
     * @param bool|callable $value The boolean value or a callable to get a boolean value from.
     * @return self
     * @throws InvalidArgumentException for non-boolean or non-callable value.
     */
    public function setEnabled($value): self
    {
        if (!is_bool($value) && !is_callable($value)) {
            throw new InvalidArgumentException(sprintf(
                "The value indicating whether this log target is enabled must be a boolean or callable, %s received.",
                gettype($value)
            ));
        }

        $this->enabled = $value;
        return $this;
    }

    /**
     * Enables the log target.
     *
     * @return self
     */
    public function enable(): self
    {
        return $this->setEnabled(true);
    }

    /**
     * Disables the log target.
     *
     * @return self
     */
    public function disable(): self
    {
        return $this->setEnabled(false);
    }

    /**
     * Check whether the log target is enabled.
     *
     * @return bool The value indicating whether this log target is enabled.
     */
    public function isEnabled(): bool
    {
        if (is_callable($this->enabled)) {
            return ($this->enabled)($this);
        }

        return $this->enabled;
    }

    /**
     * Generates the context information to be logged.
     *
     * The default implementation will dump user information, system variables, etc.
     *
     * @return string The context information. If an empty string, it means no context information.
     */
    protected function getContextMessage(): string
    {
        $result = [];

        foreach (ArrayHelper::filter($GLOBALS, $this->logVars) as $key => $value) {
            $result[] = "\${$key} = " . VarDumper::create($value)->asString();
        }

        return implode("\n\n", $result);
    }

    /**
     * Filters the given messages according to their categories and levels.
     *
     * The message structure follows that in {@see MessageGroupInterface::add()}.
     *
     * @param array[] $messages List log messages to be filtered.
     * @return array[] The filtered log messages.
     */
    protected function filterMessages(array $messages): array
    {
        foreach ($messages as $i => $message) {
            $levels = $this->messages->getLevels();

            if ((!empty($levels) && !in_array(($message[0] ?? ''), $levels, true))) {
                unset($messages[$i]);
                continue;
            }

            $category = (string) ($message[2]['category'] ?? '');

            if (!$this->categories->isAllowed($category) || $this->categories->isExcepted($category)) {
                unset($messages[$i]);
            }
        }

        return $messages;
    }

    /**
     * Formats a log message for display as a string.
     *
     * The message structure follows that in {@see MessageGroupInterface::add()}.
     *
     * @param array $message The log message to be formatted.
     * @return string The formatted log message.
     */
    protected function formatMessage(array $message): string
    {
        [$level, $text, $context] = $message;
        $level = Logger::getLevelName($level);
        $timestamp = $context['time'] ?? microtime(true);
        $category = $context['category'] ?? MessageCategory::DEFAULT;

        $traces = [];
        if (isset($context['trace']) && is_array($context['trace'])) {
            foreach ($context['trace'] as $trace) {
                if (isset($trace['file'], $trace['line'])) {
                    $traces[] = "in {$trace['file']}:{$trace['line']}";
                }
            }
        }

        $prefix = $this->getMessagePrefix($message);

        return $this->getTime($timestamp) . " {$prefix}[$level][$category] $text"
            . (empty($traces) ? '' : "\n    " . implode("\n    ", $traces));
    }

    /**
     * Gets a string to be prefixed to the given message.
     *
     * If {@see Target::$prefix} is configured it will return the result of the callback.
     * The default implementation will return user IP, user ID and session ID as a prefix.
     * The message structure follows that in {@see MessageGroupInterface::add()}.
     *
     * @param array $message The log message being exported.
     * @return string The log  prefix string.
     */
    protected function getMessagePrefix(array $message): string
    {
        if ($this->prefix !== null) {
            return ($this->prefix)($message);
        }

        return '';
    }

    /**
     * Gets formatted timestamp for message, according to {@see Target::$timestampFormat}.
     *
     * @param float|int $timestamp The timestamp to be formatted.
     * @return string Formatted timestamp for message.
     */
    protected function getTime($timestamp): string
    {
        $timestamp = (string) $timestamp;
        $format = strpos($timestamp, '.') === false ? 'U' : 'U.u';
        return DateTime::createFromFormat($format, $timestamp)->format($this->timestampFormat);
    }
}
