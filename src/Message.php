<?php

declare(strict_types=1);

namespace Yiisoft\Log;

use LogicException;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use Stringable;
use Yiisoft\Log\Message\ContextValueExtractor;
use Yiisoft\VarDumper\VarDumper;

use function preg_replace_callback;

/**
 * Message is a data object that stores log message data.
 */
final class Message
{
    public const DEFAULT_CATEGORY = 'application';

    /**
     * @var string Log message level.
     *
     * @see LogLevel See constants for valid level names.
     */
    private string $level;

    /**
     * @var string Log message.
     */
    private string $message;

    /**
     * @var array Log message context.
     *
     * Message context has a following keys:
     *
     * - category: string, message category.
     * - memory: int, memory usage in bytes, obtained by `memory_get_usage()`.
     * - time: float, message timestamp obtained by microtime(true).
     * - trace: array, debug backtrace, contains the application code call stacks.
     */
    private array $context;

    /**
     * @param string $level Log message level.
     * @param string|Stringable $message Log message.
     * @param array $context Log message context.
     *
     * @throws InvalidArgumentException for invalid log message level.
     *
     * @see LoggerTrait::log()
     * @see LogLevel
     */
    public function __construct(string $level, string|Stringable $message, array $context = [])
    {
        Logger::assertLevelIsSupported($level);
        $this->level = $level;
        $this->message = $this->parse($message, $context);
        $this->context = $context;
    }

    /**
     * Gets a log message level.
     *
     * @return string Log message level.
     */
    public function level(): string
    {
        return $this->level;
    }

    /**
     * Gets a log message.
     *
     * @return string Log message.
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * Returns a value of the context parameter for the specified name.
     *
     * If no name is specified, the entire context is returned.
     *
     * @param string|null $name The context parameter name.
     * @param mixed $default If the context parameter does not exist, the `$default` will be returned.
     *
     * @return mixed The context parameter value.
     */
    public function context(string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return $this->context;
        }

        return $this->context[$name] ?? $default;
    }

    /**
     * Returns the log message category. {@see self::DEFAULT_CATEGORY} is returned if the category is not set.
     *
     * @return string The log message category.
     */
    public function category(): string
    {
        $category = $this->context['category'] ?? self::DEFAULT_CATEGORY;
        if (!is_string($category)) {
            throw new LogicException(
                'Invalid category value in log context. Expected "string", got "' . get_debug_type($category) . '".'
            );
        }
        return $category;
    }

    /**
     * Parses log message resolving placeholders in the form: "{foo}",
     * where foo will be replaced by the context data in key "foo".
     *
     * @param string|Stringable $message Raw log message.
     * @param array $context Message context.
     *
     * @return string Parsed message.
     */
    private function parse(string|Stringable $message, array $context): string
    {
        $message = (string) $message;

        return preg_replace_callback(
            '/{(.*)}/',
            static function (array $matches) use ($context) {
                [$exist, $value] = ContextValueExtractor::extract($context, $matches[1]);
                if ($exist) {
                    if (
                        is_scalar($value)
                        || $value instanceof Stringable
                        || $value === null
                    ) {
                        return (string) $value;
                    }
                    return VarDumper::create($value)->asString();
                }
                return $matches[0];
            },
            $message
        );
    }
}
