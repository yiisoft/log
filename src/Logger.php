<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Log;

use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Yiisoft\VarDumper\VarDumper;

/**
 * Logger records logged messages in memory and sends them to different targets according to [[targets]].
 *
 * A Logger instance can be accessed via `Yii::getLogger()`. You can call the method [[log()]] to record a single log message.
 *
 * For more details and usage information on Logger, see the [guide article on logging](guide:runtime-logging)
 * and [PSR-3 specification](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md).
 *
 * When the application ends or [[flushInterval]] is reached, Logger will call [[flush()]]
 * to send logged messages to different log targets, such as [[FileTarget|file]], [[EmailTarget|email]],
 * or [[DbTarget|database]], according to the [[targets]].
 */
class Logger implements LoggerInterface
{
    use LoggerTrait;

    public const EMERGENCY = LogLevel::EMERGENCY;
    public const ALERT     = LogLevel::ALERT;
    public const CRITICAL  = LogLevel::CRITICAL;
    public const ERROR     = LogLevel::ERROR;
    public const WARNING   = LogLevel::WARNING;
    public const NOTICE    = LogLevel::NOTICE;
    public const INFO      = LogLevel::INFO;
    public const DEBUG     = LogLevel::DEBUG;

    /**
     * @var array logged messages. This property is managed by [[log()]] and [[flush()]].
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
     */
    public $messages = [];
    /**
     * @var int how many messages should be logged before they are flushed from memory and sent to targets.
     * Defaults to 1000, meaning the [[flush]] method will be invoked once every 1000 messages logged.
     * Set this property to be 0 if you don't want to flush messages until the application terminates.
     * This property mainly affects how much memory will be taken by the logged messages.
     * A smaller value means less memory, but will increase the execution time due to the overhead of [[flush()]].
     */
    private $flushInterval = 1000;
    /**
     * @var int how much call stack information (file name and line number) should be logged for each message.
     * If it is greater than 0, at most that number of call stacks will be logged. Note that only application
     * call stacks are counted.
     */
    private $traceLevel = 0;
    /**
     * @var array An array of paths to exclude from the trace when tracing is enabled using [[setTraceLevel()]].
     */
    private $excludedTracePaths = [];
    /**
     * @var Target[] the log targets. Each array element represents a single [[Target|log target]] instance
     */
    private $targets = [];

    /**
     * Initializes the logger by registering [[flush()]] as a shutdown function.
     *
     * @param Target[] $targets the log targets.
     */
    public function __construct(array $targets = [])
    {
        $this->setTargets($targets);

        \register_shutdown_function(function () {
            // make regular flush before other shutdown functions, which allows session data collection and so on
            $this->flush();
            // make sure log entries written by shutdown functions are also flushed
            // ensure "flush()" is called last when there are multiple shutdown functions
            \register_shutdown_function([$this, 'flush'], true);
        });
    }

    /**
     * @return Target[] the log targets. Each array element represents a single [[Target|log target]] instance.
     */
    public function getTargets(): array
    {
        return $this->targets;
    }

    /**
     * @param int|string $name string name or integer index
     * @return Target|null
     */
    public function getTarget($name): ?Target
    {
        $this->getTargets();

        return $this->targets[$name] ?? null;
    }

    /**
     * @param Target[] $targets the log targets. Each array element represents a single [[Target|log target]] instance
     * or the configuration for creating the log target instance.
     */
    public function setTargets(array $targets): void
    {
        foreach ($targets as $target) {
            if (!$target instanceof Target) {
                throw new InvalidArgumentException('You must provide an instance of \Yiisoft\Log\Target.');
            }
        }
        $this->targets = $targets;
    }

    /**
     * Adds extra target to [[targets]].
     * @param Target $target the log target instance.
     * @param string|null $name array key to be used to store target, if `null` is given target will be append
     * to the end of the array by natural integer key.
     */
    public function addTarget(Target $target, string $name = null)
    {
        if ($name === null) {
            $this->targets[] = $target;
        } else {
            $this->targets[$name] = $target;
        }
    }

    /**
     * Prepares message for logging.
     */
    public static function prepareMessage($message)
    {
        if (method_exists($message, '__toString')) {
            return $message->__toString();
        }

        if (is_scalar($message)) {
            return (string)$message;
        }

        return VarDumper::export($message);
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = [])
    {
        if ($message instanceof \Throwable) {
            if (!isset($context['exception'])) {
                // exceptions are string-convertible, thus should be passed as it is to the logger
                // if exception instance is given to produce a stack trace, it MUST be in a key named "exception".
                $context['exception'] = $message;
            }
        }
        $message = static::prepareMessage($message);

        if (!isset($context['time'])) {
            $context['time'] = microtime(true);
        }
        if (!isset($context['trace'])) {
            $traces = [];
            if ($this->traceLevel > 0) {
                $count = 0;
                foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $trace) {
                    if (isset($trace['file'], $trace['line'])) {
                        $excludedMatch = array_filter($this->excludedTracePaths, static function ($path) use ($trace) {
                            return strpos($trace['file'], $path) !== false;
                        });

                        if (empty($excludedMatch)) {
                            unset($trace['object'], $trace['args']);
                            $traces[] = $trace;
                            if (++$count >= $this->traceLevel) {
                                break;
                            }
                        }
                    }
                }
            }
            $context['trace'] = $traces;
        }

        if (!isset($context['memory'])) {
            $context['memory'] = memory_get_usage();
        }

        if (!isset($context['category'])) {
            $context['category'] = 'application';
        }

        $message = $this->parseMessage($message, $context);

        $this->messages[] = [$level, $message, $context];

        if ($this->flushInterval > 0 && count($this->messages) >= $this->flushInterval) {
            $this->flush();
        }
    }

    /**
     * Flushes log messages from memory to targets.
     * @param bool $final whether this is a final call during a request.
     */
    public function flush(bool $final = false): void
    {
        $messages = $this->messages;
        // https://github.com/yiisoft/yii2/issues/5619
        // new messages could be logged while the existing ones are being handled by targets
        $this->messages = [];

        $this->dispatch($messages, $final);
    }

    /**
     * Dispatches the logged messages to [[targets]].
     * @param array $messages the logged messages
     * @param bool $final whether this method is called at the end of the current application
     */
    protected function dispatch($messages, bool $final): void
    {
        $targetErrors = [];
        foreach ($this->getTargets() as $target) {
            if ($target->isEnabled()) {
                try {
                    $target->collect($messages, $final);
                } catch (\Exception $e) {
                    $target->disable();
                    $targetErrors[] = [
                        'Unable to send log via ' . get_class($target) . ': ' . get_class($e) . ': ' . $e->getMessage(),
                        LogLevel::WARNING,
                        __METHOD__,
                        microtime(true),
                        [],
                    ];
                }
            }
        }

        if (!empty($targetErrors)) {
            $this->dispatch($targetErrors, true);
        }
    }

    /**
     * Parses log message resolving placeholders in the form: '{foo}', where foo
     * will be replaced by the context data in key "foo".
     * @param string $message log message.
     * @param array $context message context.
     * @return string parsed message.
     */
    protected function parseMessage(string $message, array $context): string
    {
        return preg_replace_callback('/\\{([\\w\\.]+)\\}/is', static function ($matches) use ($context) {
            $placeholderName = $matches[1];
            if (isset($context[$placeholderName])) {
                return (string)$context[$placeholderName];
            }
            return $matches[0];
        }, $message);
    }

    /**
     * Returns the total elapsed time since the start of the current request.
     * This method calculates the difference between now and the start of the
     * request ($_SERVER['REQUEST_TIME_FLOAT']).
     * @return float the total elapsed time in seconds for current request.
     */
    public function getElapsedTime(): float
    {
        return \microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    }

    /**
     * Returns the text display of the specified level.
     * @param mixed $level the message level, e.g. [[LogLevel::ERROR]], [[LogLevel::WARNING]].
     * @return string the text display of the level
     */
    public static function getLevelName($level): string
    {
        if (is_string($level)) {
            return $level;
        }
        return 'unknown';
    }

    /**
     * @return int
     */
    public function getFlushInterval(): int
    {
        return $this->flushInterval;
    }

    /**
     * @param int $flushInterval
     * @return Logger
     */
    public function setFlushInterval(int $flushInterval): self
    {
        $this->flushInterval = $flushInterval;
        return $this;
    }

    /**
     * @return int
     */
    public function getTraceLevel(): int
    {
        return $this->traceLevel;
    }

    /**
     * @param int $traceLevel
     * @return Logger
     */
    public function setTraceLevel(int $traceLevel): self
    {
        $this->traceLevel = $traceLevel;
        return $this;
    }

    /**
     * @param array $excludedTracePaths
     * @return Logger
     */
    public function setExcludedTracePaths(array $excludedTracePaths): self
    {
        $this->excludedTracePaths = $excludedTracePaths;
        return $this;
    }
}
