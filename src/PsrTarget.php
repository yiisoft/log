<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Log;

use Psr\Log\LoggerInterface;

/**
 * PsrTarget is a log target which simply passes messages to another PSR-3 compatible logger,
 * which is specified via [[$logger]].
 *
 * Application configuration example:
 *
 * ```php
 * return [
 *     'logger' => [
 *         'targets' => [
 *             [
 *                 '__class' => Yiisoft\Log\PsrTarget::class,
 *                 'logger' => function () {
 *                     $logger = new \Monolog\Logger('my_logger');
 *                     $logger->pushHandler(new \Monolog\Handler\SlackHandler('slack_token', 'logs', null, true, null, \Monolog\Logger::DEBUG));
 *                     return $logger;
 *                 },
 *             ],
 *         ],
 *         // ...
 *     ],
 *     // ...
 * ];
 * ```
 *
 * > Warning: make sure logger specified via [[$logger]] is not the same as the Yii logger, otherwise
 *   your program may fall into infinite loop.
 *
 * @property LoggerInterface $logger logger to be used by this target. Refer to [[setLogger()]] for details.
 */
class PsrTarget extends Target
{
    /**
     * @var LoggerInterface logger instance to be used for messages processing.
     */
    private $_logger;

    /**
     * Sets the PSR-3 logger used to save messages of this target.
     * @param LoggerInterface $logger logger instance.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->_logger = $logger;
    }

    /**
     * @return LoggerInterface logger instance.
     */
    public function getLogger(): LoggerInterface
    {
        return $this->_logger;
    }

    /**
     * {@inheritdoc}
     */
    public function export(): void
    {
        foreach ($this->messages as $message) {
            [$level, $text, $context] = $message;
            $this->getLogger()->log($level, $text, $context);
        }
    }
}
