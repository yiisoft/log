<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yii\Log;

/**
 * LogRuntimeException represents an exception caused by problems with log delivery.
 *
 * @author Bizley <pawel@positive.codes>
 */
class LogRuntimeException extends \yii\exceptions\Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Log Runtime';
    }
}
