<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Log;

use Psr\Log\LogLevel;
use yii\base\Component;
use Yiisoft\Arrays\ArrayHelper;
use yii\helpers\VarDumper;

/**
 * Target is the base class for all log target classes.
 *
 * A log target object will filter the messages logged by [[Logger]] according
 * to its [[levels]] and [[categories]] properties. It may also export the filtered
 * messages to specific destination defined by the target, such as emails, files.
 *
 * Level filter and category filter are combinatorial, i.e., only messages
 * satisfying both filter conditions will be handled. Additionally, you
 * may specify [[except]] to exclude messages of certain categories.
 *
 * For more details and usage information on Target, see the [guide article on logging & targets](guide:runtime-logging).
 *
 * @property bool $enabled Whether to enable this log target. Defaults to true.
 */
abstract class Target extends Component
{
    /**
     * @var array list of message categories that this target is interested in. Defaults to empty, meaning all categories.
     * You can use an asterisk at the end of a category so that the category may be used to
     * match those categories sharing the same common prefix. For example, 'yii\db\*' will match
     * categories starting with 'yii\db\', such as `yii\db\Connection`.
     */
    public $categories = [];
    /**
     * @var array list of message categories that this target is NOT interested in. Defaults to empty, meaning no uninteresting messages.
     * If this property is not empty, then any category listed here will be excluded from [[categories]].
     * You can use an asterisk at the end of a category so that the category can be used to
     * match those categories sharing the same common prefix. For example, 'yii\db\*' will match
     * categories starting with 'yii\db\', such as `yii\db\Connection`.
     * @see categories
     */
    public $except = [];
    /**
     * @var array the message levels that this target is interested in.
     *
     * The parameter should be an array of interested level names. See [[LogLevel]] constants for valid level names.
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
    public $levels = [];
    /**
     * @var array list of the PHP predefined variables that should be logged in a message.
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
     * Note that if you need $_SESSION to logged regardless if session was used you have to open it right at
     * the start of your request.
     *
     * @see \Yiisoft\Arrays\ArrayHelper::filter()
     */
    public $logVars = ['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER'];
    /**
     * @var callable a PHP callable that returns a string to be prefixed to every exported message.
     *
     * If not set, [[getMessagePrefix()]] will be used, which prefixes the message with context information
     * such as user IP, user ID and session ID.
     *
     * The signature of the callable should be `function ($message)`.
     */
    public $prefix;
    /**
     * @var int how many messages should be accumulated before they are exported.
     * Defaults to 1000. Note that messages will always be exported when the application terminates.
     * Set this property to be 0 if you don't want to export messages until the application terminates.
     */
    public $exportInterval = 1000;
    /**
     * @var array the messages that are retrieved from the logger so far by this log target.
     * Please refer to [[Logger::messages]] for the details about the message structure.
     */
    public $messages = [];
    /**
     * @var bool whether to log time with microseconds.
     * Defaults to false.
     */
    public $microtime = false;

    /**
     * @var bool
     */
    private $_enabled = true;


    /**
     * Exports log [[messages]] to a specific destination.
     * Child classes must implement this method.
     */
    abstract public function export();

    /**
     * Processes the given log messages.
     * This method will filter the given messages with [[levels]] and [[categories]].
     * And if requested, it will also export the filtering result to specific medium (e.g. email).
     * @param array $messages log messages to be processed. See [[Logger::messages]] for the structure
     * of each message.
     * @param bool $final whether this method is called at the end of the current application
     */
    public function collect($messages, bool $final): void
    {
        $this->messages = array_merge($this->messages, static::filterMessages($messages, $this->levels, $this->categories, $this->except));
        $count = count($this->messages);
        if ($count > 0 && ($final || $this->exportInterval > 0 && $count >= $this->exportInterval)) {
            if (($context = $this->getContextMessage()) !== '') {
                $this->messages[] = [LogLevel::INFO, $context, ['category' => 'application', 'time' => YII_BEGIN_TIME]];
            }
            // set exportInterval to 0 to avoid triggering export again while exporting
            $oldExportInterval = $this->exportInterval;
            $this->exportInterval = 0;
            $this->export();
            $this->exportInterval = $oldExportInterval;

            $this->messages = [];
        }
    }

    /**
     * Generates the context information to be logged.
     * The default implementation will dump user information, system variables, etc.
     * @return string the context information. If an empty string, it means no context information.
     */
    protected function getContextMessage(): string
    {
        $context = ArrayHelper::filter($GLOBALS, $this->logVars);
        $result = [];
        foreach ($context as $key => $value) {
            $result[] = "\${$key} = " . VarDumper::dumpAsString($value);
        }

        return implode("\n\n", $result);
    }

    /**
     * Filters the given messages according to their categories and levels.
     * @param array $messages messages to be filtered.
     * The message structure follows that in [[Logger::messages]].
     * @param array $levels the message levels to filter by. Empty value means allowing all levels.
     * @param array $categories the message categories to filter by. If empty, it means all categories are allowed.
     * @param array $except the message categories to exclude. If empty, it means all categories are allowed.
     * @return array the filtered messages.
     */
    public static function filterMessages($messages, array $levels = [], array $categories = [], array $except = []): array
    {
        foreach ($messages as $i => $message) {
            if (!empty($levels) && !in_array($message[0], $levels, true)) {
                unset($messages[$i]);
                continue;
            }

            $matched = empty($categories);
            foreach ($categories as $category) {
                if ($message[2]['category'] === $category || !empty($category) && substr_compare($category, '*', -1, 1) === 0 && strpos($message[2]['category'], rtrim($category, '*')) === 0) {
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                foreach ($except as $category) {
                    $prefix = rtrim($category, '*');
                    if (($message[2]['category'] === $category || $prefix !== $category) && strpos($message[2]['category'], $prefix) === 0) {
                        $matched = false;
                        break;
                    }
                }
            }

            if (!$matched) {
                unset($messages[$i]);
            }
        }

        return $messages;
    }

    /**
     * Formats a log message for display as a string.
     * @param array $message the log message to be formatted.
     * The message structure follows that in [[Logger::messages]].
     * @return string the formatted message
     */
    public function formatMessage(array $message): string
    {
        [$level, $text, $context] = $message;
        $category = $context['category'];
        $timestamp = $context['time'];
        $level = Logger::getLevelName($level);
        $traces = [];
        if (isset($context['trace'])) {
            foreach ($context['trace'] as $trace) {
                $traces[] = "in {$trace['file']}:{$trace['line']}";
            }
        }

        $prefix = $this->getMessagePrefix($message);

        return $this->getTime($timestamp) . " {$prefix}[$level][$category] $text"
            . (empty($traces) ? '' : "\n    " . implode("\n    ", $traces));
    }

    /**
     * Returns a string to be prefixed to the given message.
     * If [[prefix]] is configured it will return the result of the callback.
     * The default implementation will return user IP, user ID and session ID as a prefix.
     * @param array $message the message being exported.
     * The message structure follows that in [[Logger::messages]].
     * @return string the prefix string
     * @throws \Throwable
     */
    public function getMessagePrefix(array $message): string
    {
        if ($this->prefix !== null) {
            return call_user_func($this->prefix, $message);
        }

        return '';
    }

    /**
     * Sets a value indicating whether this log target is enabled.
     * @param bool|callable $value a boolean value or a callable to obtain the value from.
     *
     * A callable may be used to determine whether the log target should be enabled in a dynamic way.
     * For example, to only enable a log if the current user is logged in you can configure the target
     * as follows:
     *
     * ```php
     * 'enabled' => function() {
     *     return !Yii::getApp()->user->isGuest;
     * }
     * ```
     */
    public function setEnabled($value): void
    {
        $this->_enabled = $value;
    }

    /**
     * Check whether the log target is enabled.
     * @property bool Indicates whether this log target is enabled. Defaults to true.
     * @return bool A value indicating whether this log target is enabled.
     */
    public function getEnabled(): bool
    {
        if (is_callable($this->_enabled)) {
            return call_user_func($this->_enabled, $this);
        }

        return $this->_enabled;
    }

    /**
     * Returns formatted ('Y-m-d H:i:s') timestamp for message.
     * If [[microtime]] is configured to true it will return format 'Y-m-d H:i:s.u'.
     * @param float $timestamp
     * @return string
     */
    protected function getTime($timestamp): string
    {
        $toString = \str_replace(',', '.', (string) $timestamp);
        $parts = explode('.', $toString);

        return date('Y-m-d H:i:s', $parts[0]) . ($this->microtime && isset($parts[1]) ? ('.' . $parts[1]) : '');
    }
}
