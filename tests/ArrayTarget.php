<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Log\Tests;

use Yiisoft\Log\Target;

/**
 * ArrayTarget logs messages into an array, useful for tracking data in tests.
 */
class ArrayTarget extends Target
{
    protected $exportInterval = 1000000;

    /**
     * Exports log [[messages]] to a specific destination.
     */
    public function export(): void
    {
        // throw exception if message limit is reached
        throw new \Exception('More than 1000000 messages logged.');
    }
}
