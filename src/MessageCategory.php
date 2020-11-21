<?php

declare(strict_types=1);

namespace Yiisoft\Log;

use Psr\Log\InvalidArgumentException;

use function gettype;
use function is_string;
use function rtrim;
use function substr_compare;
use function sprintf;
use function strpos;

/**
 * Category is a data object that stores and matches the allowed and excepted categories of log messages.
 */
final class MessageCategory
{
    public const DEFAULT = 'application';

    /**
     * @var string[] List of allowed log message categories.
     *
     * Defaults to empty, which means all categories are allowed.
     *
     * You can use an asterisk at the end of a category so that the category may be used to
     * match those categories sharing the same common prefix. For example, 'Yiisoft\Db\*' will match
     * categories starting with 'Yiisoft\Db\', such as `Yiisoft\Db\Connection`.
     */
    private array $allowed = [];

    /**
     * @var string[] List of excepted log message categories.
     *
     * Defaults to empty, which means there are no excepted categories.
     *
     * You can use an asterisk at the end of a category so that the category can be used to
     * match those categories sharing the same common prefix. For example, 'Yiisoft\Db\*' will match
     * categories starting with 'Yiisoft\Db\', such as `Yiisoft\Db\Connection`.
     */
    private array $excepted = [];

    /**
     * Sets the log message categories to be allowed.
     *
     * @param string[] $categories List of log message categories to be allowed.
     */
    public function setAllowed(array $categories): void
    {
        $this->checkStructure($categories);
        $this->allowed = $categories;
    }

    /**
     * Gets the log message categories to be allowed.
     *
     * @return string[] List of log message categories to be allowed.
     */
    public function getAllowed(): array
    {
        return $this->allowed;
    }

    /**
     * Checks whether the specified log message category is allowed.
     *
     * @param string $category Log message category.
     * @return bool The value indicating whether the specified category is allowed.
     */
    public function isAllowed(string $category): bool
    {
        foreach ($this->allowed as $allowed) {
            if (
                ($category && $category === $allowed)
                || (
                    !empty($allowed)
                    && substr_compare($allowed, '*', -1, 1) === 0
                    && strpos($category, rtrim($allowed, '*')) === 0
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sets the log message categories to be excepted.
     *
     * @param string[] $categories List of log message categories to be excepted.
     */
    public function setExcepted(array $categories): void
    {
        $this->excepted = $categories;
    }

    /**
     * Gets the log message categories to be excepted.
     *
     * @return string[] List of log message categories to be excepted.
     */
    public function getExcepted(): array
    {
        return $this->excepted;
    }

    /**
     * Checks whether the specified log message category is excepted.
     *
     * @param string $category Log message category.
     * @return bool The value indicating whether the specified category is excepted.
     */
    public function isExcepted(string $category): bool
    {
        foreach ($this->excepted as $excepted) {
            $prefix = rtrim($excepted, '*');

            if (
                (($category && $category === $excepted) || $prefix !== $excepted)
                && strpos($category, $prefix) === 0
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks message categories structure.
     *
     * @param array $categories The log message categories to be checked.
     * @throws InvalidArgumentException for invalid log message categories structure.
     */
    private function checkStructure(array $categories): void
    {
        foreach ($categories as $category) {
            if (!is_string($category)) {
                throw new InvalidArgumentException(sprintf(
                    "The log message category must be a string, %s received.",
                    gettype($category)
                ));
            }
        }
    }
}
