<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Log;

/**
 * LogRuntimeException represents an exception caused by problems with log delivery.
 */
class LogRuntimeException extends \RuntimeException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Log Runtime';
    }
}
