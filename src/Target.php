<?php

declare(strict_types=1);

namespace Yiisoft\Log;

use InvalidArgumentException;
use RuntimeException;
use Yiisoft\Arrays\ArrayHelper;

use function gettype;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function sprintf;

/**
 * Target is the base class for all log target classes.
 *
 * A log target object will filter the messages logged by {@see \Yiisoft\Log\Logger} according
 * to its {@see Target::setLevels()} and {@see Target::setCategories()}. It may also export
 * the filtered messages to specific destination defined by the target, such as emails, files.
 *
 * Level filter and category filter are combinatorial, i.e., only messages
 * satisfying both filter conditions will be handled. Additionally, you may
 * specify {@see Target::setExcept()} to exclude messages of certain categories.
 */
abstract class Target
{
    private MessageCategoryFilter $categories;
    private MessageCollection $messages;
    private MessageFormatter $formatter;

    /**
     * @var string[] List of the PHP predefined variables that should be logged in a message.
     *
     * This data will be available in the context of the log message using the "globals" key.
     *
     * Note that a variable must be accessible via `$GLOBALS`. Otherwise it won't be logged.
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
     * @see MessageCollection::$messages
     */
    private array $logGlobals = ['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER'];

    /**
     * @var array The list of user parameters in the `key => value` format.
     *
     * This data will be available in the context of the log message using the "params" key.
     *
     * @see MessageCollection::$messages
     */
    private array $logParams = [];

    /**
     * @var int How many log messages should be accumulated before they are exported.
     *
     * Defaults to 1000. Note that messages will always be exported when the application terminates.
     * Set this property to be 0 if you don't want to export messages until the application terminates.
     */
    private int $exportInterval = 1000;

    /**
     * @var bool|callable Enables or disables the current target to export.
     */
    private $enabled = true;

    /**
     * Exports log messages to a specific destination.
     * Child classes must implement this method.
     */
    abstract protected function export(): void;

    /**
     * When defining a constructor in child classes, you must call `parent::__construct()`.
     */
    public function __construct()
    {
        $this->categories = new MessageCategoryFilter();
        $this->messages = new MessageCollection();
        $this->formatter = new MessageFormatter();
    }

    /**
     * Processes the given log messages.
     *
     * This method will filter the given messages with levels and categories.
     * The message structure follows that in {@see MessageCollection::$messages}.
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
     *
     * @throws InvalidArgumentException for invalid log message categories structure.
     *
     * @return self
     *
     * @see MessageCategoryFilter::$include
     */
    public function setCategories(array $categories): self
    {
        $this->categories->include($categories);
        return $this;
    }

    /**
     * Sets a list of log message categories that this target is NOT interested in.
     *
     * @param array $except The list of log message categories.
     *
     * @throws InvalidArgumentException for invalid log message categories structure.
     *
     * @return self
     *
     * @see MessageCategoryFilter::$exclude
     */
    public function setExcept(array $except): self
    {
        $this->categories->exclude($except);
        return $this;
    }

    /**
     * Sets a list of log message levels that current target is interested in.
     *
     * @param array $levels The list of log message levels.
     *
     * @throws InvalidArgumentException for invalid log message level.
     *
     * @return self
     *
     * @see MessageCollection::$levels
     */
    public function setLevels(array $levels): self
    {
        $this->messages->setLevels($levels);
        return $this;
    }

    /**
     * Sets a list of the predefined PHP global variables that should be logged in a message.
     *
     * @param array $logGlobals The list of PHP global variables.
     *
     * @throws InvalidArgumentException for non-string values.
     *
     * @return self
     *
     * @see Target::$logGlobals
     */
    public function setLogGlobals(array $logGlobals): self
    {
        $this->checkGlobalNames($logGlobals);
        $this->logGlobals = $logGlobals;
        return $this;
    }

    /**
     * Sets a list of user parameters in the `key => value` format.
     *
     * @param array $logParams The list of user parameters.
     *
     * @return self
     *
     * @see Target::$logParams
     */
    public function setLogParams(array $logParams): self
    {
        $this->logParams = $logParams;
        return $this;
    }

    /**
     * Sets a PHP callable that returns a string representation of the log message.
     *
     * @param callable $format The PHP callable to get a string value from.
     *
     * @return self
     *
     * @see MessageFormatter::$format
     */
    public function setFormat(callable $format): self
    {
        $this->formatter->setFormat($format);
        return $this;
    }

    /**
     * Sets a PHP callable that returns a string to be prefixed to every exported message.
     *
     * @param callable $prefix The PHP callable to get a string prefix of the log message.
     *
     * @return self
     *
     * @see MessageFormatter::$prefix
     */
    public function setPrefix(callable $prefix): self
    {
        $this->formatter->setPrefix($prefix);
        return $this;
    }

    /**
     * Sets how many messages should be accumulated before they are exported.
     *
     * @param int $exportInterval The number of log messages to accumulate before exporting.
     *
     * @return self
     *
     * @see Target::$exportInterval
     */
    public function setExportInterval(int $exportInterval): self
    {
        $this->exportInterval = $exportInterval;
        return $this;
    }

    /**
     * Sets a date format for the log timestamp.
     *
     * @param string $format The date format for the log timestamp.
     *
     * @return self
     *
     * @see Target::$timestampFormat
     */
    public function setTimestampFormat(string $format): self
    {
        $this->formatter->setTimestampFormat($format);
        return $this;
    }

    /**
     * Sets a PHP callable that returns a boolean indicating whether this log target is enabled.
     *
     * @param callable $value The PHP callable to get a boolean value.
     *
     * @return self
     *
     * @see Target::$enabled
     */
    public function setEnabled(callable $value): self
    {
        $this->enabled = $value;
        return $this;
    }

    /**
     * Enables the log target.
     *
     * @return self
     *
     * @see Target::$enabled
     */
    public function enable(): self
    {
        $this->enabled = true;
        return $this;
    }

    /**
     * Disables the log target.
     *
     * @return self
     *
     * @see Target::$enabled
     */
    public function disable(): self
    {
        $this->enabled = false;
        return $this;
    }

    /**
     * Check whether the log target is enabled.
     *
     * @throws RuntimeException for a callable "enabled" that does not return a boolean.
     *
     * @return bool The value indicating whether this log target is enabled.
     *
     * @see Target::$enabled
     */
    public function isEnabled(): bool
    {
        if (is_bool($this->enabled)) {
            return $this->enabled;
        }

        if (!is_bool($enabled = ($this->enabled)($this))) {
            throw new RuntimeException(sprintf(
                'The PHP callable "enabled" must returns a boolean, %s received.',
                gettype($enabled)
            ));
        }

        return $enabled;
    }

    /**
     * Gets a list of log messages that are retrieved from the logger so far by this log target.
     *
     * @return array[] The list of log messages.
     *
     * @see MessageCollection::$messages
     */
    protected function getMessages(): array
    {
        return $this->messages->all();
    }

    /**
     * Gets a list of formatted log messages.
     *
     * @return array The list of formatted log messages.
     */
    protected function getFormattedMessages(): array
    {
        $formatted = [];

        foreach ($this->messages->all() as $key => $message) {
            $formatted[$key] = $this->formatter->format($message);
        }

        return $formatted;
    }

    /**
     * Formats all log messages for display as a string.
     *
     * @param string $separator The log messages string separator.
     *
     * @return string The string formatted log messages.
     */
    protected function formatMessages(string $separator = ''): string
    {
        $formatted = '';

        foreach ($this->messages->all() as $message) {
            $formatted .= $this->formatter->format($message) . $separator;
        }

        return $formatted;
    }

    /**
     * Filters the given messages according to their categories and levels.
     *
     * The message structure follows that in {@see MessageCollection::$messages}.
     *
     * @param array[] $messages List log messages to be filtered.
     *
     * @return array[] The filtered log messages.
     */
    private function filterMessages(array $messages): array
    {
        foreach ($messages as $i => $message) {
            $levels = $this->messages->getLevels();

            if ((!empty($levels) && !in_array(($message[0] ?? ''), $levels, true))) {
                unset($messages[$i]);
                continue;
            }

            if ($this->categories->isExcluded((string) ($message[2]['category'] ?? ''))) {
                unset($messages[$i]);
                continue;
            }

            if (!isset($message[2]['params']) || !is_array($message[2]['params'])) {
                $message[2]['params'] = $this->logParams;
            }

            if (isset($message[2]['globals']) && is_array($message[2]['globals'])) {
                $this->checkGlobalNames($message[2]['globals']);
                $globals = $message[2]['globals'];
            }

            $message[2]['globals'] = ArrayHelper::filter($GLOBALS, $globals ?? $this->logGlobals);
            $messages[$i] = $message;
        }

        return $messages;
    }

    /**
     * Checks PHP global variable names.
     *
     * @param array $logGlobals The list of PHP global variable names to be checked.
     *
     * @throws InvalidArgumentException for non-string values or empty string.
     *
     * @see Target::$logGlobals
     */
    private function checkGlobalNames(array $logGlobals): void
    {
        foreach ($logGlobals as $logGlobal) {
            if (!is_string($logGlobal) || $logGlobal === '') {
                throw new InvalidArgumentException(sprintf(
                    'The PHP global variable name must be a not empty string, %s received.',
                    gettype($logGlobal)
                ));
            }
        }
    }
}
