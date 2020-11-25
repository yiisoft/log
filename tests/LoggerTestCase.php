<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Yiisoft\Log\Logger;
use Yiisoft\Log\MessageCollection;

abstract class LoggerTestCase extends TestCase
{
    private string $propertyName = 'messages';

    /**
     * Sets an inaccessible object property to a designated value.
     *
     * @param Logger $logger
     * @param array $messages
     * @param bool $revoke whether to make property inaccessible after setting.
     * @throws ReflectionException
     */
    protected function setInaccessibleMessages(Logger $logger, array $messages, bool $revoke = true): void
    {
        $class = new ReflectionClass($logger);
        $collection = new MessageCollection();
        $collection->addMultiple($messages);

        $property = $class->getProperty($this->propertyName);
        $property->setAccessible(true);
        $property->setValue($logger, $collection);

        if ($revoke) {
            $property->setAccessible(false);
        }
    }

    /**
     * Gets an inaccessible object property.
     *
     * @param Logger $logger
     * @param bool $revoke whether to make property inaccessible after getting.
     * @return mixed
     * @throws ReflectionException
     */
    protected function getInaccessibleMessages(Logger $logger, bool $revoke = true)
    {
        $class = new ReflectionClass($logger);
        $property = $class->getProperty($this->propertyName);
        $property->setAccessible(true);
        /** @var MessageCollection $result */
        $result = $property->getValue($logger);

        if ($revoke) {
            $property->setAccessible(false);
        }

        return $result->all();
    }
}
