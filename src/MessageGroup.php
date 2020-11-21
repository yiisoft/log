<?php

declare(strict_types=1);

namespace Yiisoft\Log;

use Psr\Log\InvalidArgumentException;
use Yiisoft\VarDumper\VarDumper;

use function count;
use function gettype;
use function is_array;
use function is_scalar;
use function is_string;
use function method_exists;
use function preg_replace_callback;

final class MessageGroup implements MessageGroupInterface
{
    /**
     * @var string[] Log message levels that current group is interested in.
     * @see MessageGroupInterface::setLevels()
     */
    private array $levels = [];

    /**
     * @var array Log messages.
     * @see MessageGroupInterface::add()
     */
    private array $messages = [];

    public function add(string $level, $message, array $context = []): void
    {
        $this->messages[] = [$level, $this->parse($this->prepare($message), $context), $context];
    }

    public function addMultiple(array $messages): void
    {
        foreach ($messages as $message) {
            if (!isset($message[0], $message[1], $message[2]) || !is_string($message[0]) || !is_array($message[2])) {
                throw new InvalidArgumentException('The message structure is not valid.');
            }

            $this->add($message[0], $message[1], $message[2]);
        }
    }

    public function all(): array
    {
        return $this->messages;
    }

    public function clear(): void
    {
        $this->messages = [];
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function setLevels(array $levels): void
    {
        foreach ($levels as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException(sprintf(
                    "The log message levels must be a string, %s received.",
                    gettype($item)
                ));
            }
        }

        $this->levels = $levels;
    }

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
