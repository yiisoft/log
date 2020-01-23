<?php

namespace Yiisoft\Log\Tests;

use Yiisoft\Log\Target;

/**
 * ArrayTarget logs messages into an array, useful for tracking data in tests.
 */
class ArrayTarget extends Target
{
    public function __construct()
    {
        $this->setExportInterval(1000000);
    }

    /**
     * Exports log [[messages]] to a specific destination.
     */
    public function export(): void
    {
        // throw exception if message limit is reached
        throw new \Exception('More than 1000000 messages logged.');
    }
}
