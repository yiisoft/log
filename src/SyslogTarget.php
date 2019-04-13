<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yii\Log;

use Psr\Log\LogLevel;

/**
 * SyslogTarget writes log to syslog.
 *
 * @author miramir <gmiramir@gmail.com>
 */
class SyslogTarget extends Target
{
    /**
     * @var string syslog identity
     */
    public $identity;
    /**
     * @var int syslog facility.
     */
    public $facility = LOG_USER;
    /**
     * @var int openlog options. This is a bitfield passed as the `$option` parameter to [openlog()](http://php.net/openlog).
     * Defaults to `null` which means to use the default options `LOG_ODELAY | LOG_PID`.
     * @see http://php.net/openlog for available options.
     */
    public $options = LOG_ODELAY | LOG_PID;

    /**
     * @var array syslog levels
     */
    private $_syslogLevels = [
        LogLevel::EMERGENCY => LOG_EMERG,
        LogLevel::ALERT => LOG_ALERT,
        LogLevel::CRITICAL => LOG_CRIT,
        LogLevel::ERROR => LOG_ERR,
        LogLevel::WARNING => LOG_WARNING,
        LogLevel::NOTICE => LOG_NOTICE,
        LogLevel::INFO => LOG_INFO,
        LogLevel::DEBUG => LOG_DEBUG,
    ];


    /**
     * Writes log messages to syslog.
     * Starting from version 2.0.14, this method throws LogRuntimeException in case the log can not be exported.
     * @throws LogRuntimeException
     */
    public function export(): void
    {
        openlog($this->identity, $this->options, $this->facility);
        foreach ($this->messages as $message) {
            if (syslog($this->_syslogLevels[$message[0]], $this->formatMessage($message)) === false) {
                throw new LogRuntimeException('Unable to export log through system log!');
            }
        }
        closelog();
    }

    /**
     * {@inheritdoc}
     */
    public function formatMessage(array $message): string
    {
        [$level, $text, $context] = $message;
        $level = Logger::getLevelName($level);
        $prefix = $this->getMessagePrefix($message);
        return $prefix. '[' . $level . '][' . ($context['category'] ?? '') . '] ' .$text;
    }
}
