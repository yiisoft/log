<?php

declare(strict_types=1);

namespace Yiisoft\Log;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;
use Yiisoft\Log\Message\CategoryFilter;

use function array_filter;
use function count;
use function debug_backtrace;
use function gettype;
use function get_class;
use function implode;
use function in_array;
use function is_string;
use function memory_get_usage;
use function microtime;
use function register_shutdown_function;
use function sprintf;

/**
 * Logger records logged messages in memory and sends them to different targets according to {@see Logger::$targets}.
 *
 * You can call the method {@see Logger::log()} to record a single log message.
 *
 * For more details and usage information on Logger,
 * see [PSR-3 specification](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md).
 *
 * When the application ends or {@see Logger::$flushInterval} is reached, Logger will call {@see Logger::flush()}
 * to send logged messages to different log targets, such as file or email according to the {@see Logger::$targets}.
 */
final class Logger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * The list of log message levels. See {@see LogLevel} constants for valid level names.
     */
    private const LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];

    /**
     * @var Message[] The log messages.
     */
    private array $messages = [];

    /**
     * @var Target[] the log targets. Each array element represents a single {@see \Yiisoft\Log\Target} instance.
     */
    private array $targets = [];

    /**
     * @var string[] Array of paths to exclude from tracing when tracing is enabled with {@see Logger::setTraceLevel()}.
     */
    private array $excludedTracePaths = [];

    /**
     * @var int How many log messages should be logged before they are flushed from memory and sent to targets.
     *
     * Defaults to 1000, meaning the {@see Logger::flush()} method will be invoked once every 1000 messages logged.
     * Set this property to be 0 if you don't want to flush messages until the application terminates.
     * This property mainly affects how much memory will be taken by the logged messages.
     * A smaller value means less memory, but will increase the execution
     * time due to the overhead of {@see Logger::flush()}.
     */
    private int $flushInterval = 1000;

    /**
     * @var int How much call stack information (file name and line number) should be logged for each log message.
     *
     * If it is greater than 0, at most that number of call stacks will be logged.
     * Note that only application call stacks are counted.
     */
    private int $traceLevel = 0;

    /**
     * Initializes the logger by registering {@see Logger::flush()} as a shutdown function.
     *
     * @param Target[] $targets The log targets.
     */
    public function __construct(array $targets = [])
    {
        $this->setTargets($targets);

        register_shutdown_function(function () {
            // make regular flush before other shutdown functions, which allows session data collection and so on
            $this->flush();
            // make sure log entries written by shutdown functions are also flushed
            // ensure "flush()" is called last when there are multiple shutdown functions
            register_shutdown_function([$this, 'flush'], true);
        });
    }

    /**
     * Returns the text display of the specified level.
     *
     * @param mixed $level The message level, e.g. {@see LogLevel::ERROR}, {@see LogLevel::WARNING}.
     *
     * @throws \Psr\Log\InvalidArgumentException for invalid log message level.
     *
     * @return string The text display of the level.
     */
    public static function validateLevel(mixed $level): string
    {
        if (!is_string($level)) {
            throw new \Psr\Log\InvalidArgumentException(sprintf(
                'The log message level must be a string, %s provided.',
                gettype($level)
            ));
        }

        if (!in_array($level, self::LEVELS, true)) {
            throw new \Psr\Log\InvalidArgumentException(sprintf(
                'Invalid log message level "%s" provided. The following values are supported: "%s".',
                $level,
                implode('", "', self::LEVELS)
            ));
        }

        return $level;
    }

    /**
     * @return Target[] The log targets. Each array element represents a single {@see \Yiisoft\Log\Target} instance.
     */
    public function getTargets(): array
    {
        return $this->targets;
    }

    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $context['time'] ??= microtime(true);
        $context['trace'] ??= $this->collectTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
        $context['memory'] ??= memory_get_usage();
        $context['category'] ??= CategoryFilter::DEFAULT;

        $this->messages[] = new Message($level, $message, $context);

        if ($this->flushInterval > 0 && count($this->messages) >= $this->flushInterval) {
            $this->flush();
        }
    }

    /**
     * Flushes log messages from memory to targets.
     *
     * @param bool $final Whether this is a final call during a request.
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
     * Sets how many log messages should be logged before they are flushed from memory and sent to targets.
     *
     * @param int $flushInterval The number of messages to accumulate before flushing.
     *
     * @return self
     *
     * @see Logger::$flushInterval
     */
    public function setFlushInterval(int $flushInterval): self
    {
        $this->flushInterval = $flushInterval;
        return $this;
    }

    /**
     * Sets how much call stack information (file name and line number) should be logged for each log message.
     *
     * @param int $traceLevel The number of call stack information.
     *
     * @return self
     *
     * @see Logger::$traceLevel
     */
    public function setTraceLevel(int $traceLevel): self
    {
        $this->traceLevel = $traceLevel;
        return $this;
    }

    /**
     * Sets an array of paths to exclude from tracing when tracing is enabled with {@see Logger::setTraceLevel()}.
     *
     * @param array $excludedTracePaths The paths to exclude from tracing.
     *
     * @throws InvalidArgumentException for non-string values.
     *
     * @return self
     *
     * @see Logger::$excludedTracePaths
     */
    public function setExcludedTracePaths(array $excludedTracePaths): self
    {
        foreach ($excludedTracePaths as $excludedTracePath) {
            if (!is_string($excludedTracePath)) {
                throw new InvalidArgumentException(sprintf(
                    'The trace path must be a string, %s received.',
                    gettype($excludedTracePath)
                ));
            }
        }

        $this->excludedTracePaths = $excludedTracePaths;
        return $this;
    }

    /**
     * Sets a target to {@see Logger::$targets}.
     *
     * @param array $targets The log targets. Each array element represents a single {@see \Yiisoft\Log\Target}
     * instance or the configuration for creating the log target instance.
     *
     * @throws InvalidArgumentException for non-instance Target.
     */
    private function setTargets(array $targets): void
    {
        foreach ($targets as $target) {
            if (!($target instanceof Target)) {
                throw new InvalidArgumentException('You must provide an instance of \Yiisoft\Log\Target.');
            }
        }

        $this->targets = $targets;
    }

    /**
     * Dispatches the logged messages to {@see Logger::$targets}.
     *
     * @param Message[] $messages The log messages.
     * @param bool $final Whether this method is called at the end of the current application.
     */
    private function dispatch(array $messages, bool $final): void
    {
        $targetErrors = [];

        foreach ($this->targets as $target) {
            if ($target->isEnabled()) {
                try {
                    $target->collect($messages, $final);
                } catch (Throwable $e) {
                    $target->disable();
                    $targetErrors[] = new Message(
                        LogLevel::WARNING,
                        'Unable to send log via ' . get_class($target) . ': ' . get_class($e) . ': ' . $e->getMessage(),
                        ['time' => microtime(true), 'exception' => $e],
                    );
                }
            }
        }

        if (!empty($targetErrors)) {
            $this->dispatch($targetErrors, true);
        }
    }

    /**
     * Collects a trace when tracing is enabled with {@see Logger::setTraceLevel()}.
     *
     * @param array $backtrace The list of call stack information.
     *
     * @return array Collected a list of call stack information.
     */
    private function collectTrace(array $backtrace): array
    {
        $traces = [];

        if ($this->traceLevel > 0) {
            $count = 0;

            foreach ($backtrace as $trace) {
                if (isset($trace['file'], $trace['line'])) {
                    $excludedMatch = array_filter($this->excludedTracePaths, static function ($path) use ($trace) {
                        return str_contains($trace['file'], $path);
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

        return $traces;
    }
}
