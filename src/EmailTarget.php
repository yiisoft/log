<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yii\Log;

use yii\di\AbstractContainer;
use yii\exceptions\InvalidConfigException;
use yii\mail\MailerInterface;
use yii\mail\MessageInterface;

/**
 * EmailTarget sends selected log messages to the specified email addresses.
 *
 * You may configure the email to be sent by setting the [[message]] property, through which
 * you can set the target email addresses, subject, etc.:
 *
 * ```php
 * 'components' => [
 *     'log' => [
 *          'targets' => [
 *              [
 *                  '__class' => \Yii\Log\EmailTarget::class,
 *                  'mailer' => 'mailer',
 *                  'levels' => ['error', 'warning'],
 *                  'message' => [
 *                      'from' => ['log@example.com'],
 *                      'to' => ['developer1@example.com', 'developer2@example.com'],
 *                      'subject' => 'Log message',
 *                  ],
 *              ],
 *          ],
 *     ],
 * ],
 * ```
 *
 * In the above `mailer` is ID of the component that sends email and should be already configured.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 */
class EmailTarget extends Target
{
    /**
     * @var array the configuration array for creating a [[MessageInterface|message]] object.
     * Note that the "to" option must be set, which specifies the destination email address(es).
     */
    protected $message = [];
    /**
     * @var MailerInterface the mailer object.
     */
    protected $mailer = 'mailer';


    public function __construct(MailerInterface $mailer, array $message)
    {
        $this->mailer = $mailer;
        $this->message = $message;
        if (empty($this->message['to'])) {
            throw new InvalidConfigException('The "to" option must be set for EmailTarget::message.');
        }
    }

    /**
     * Sends log messages to specified email addresses.
     * Starting from version 2.0.14, this method throws LogRuntimeException in case the log can not be exported.
     * @throws LogRuntimeException
     */
    public function export(): void
    {
        // moved initialization of subject here because of the following issue
        // https://github.com/yiisoft/yii2/issues/1446
        if (empty($this->message['subject'])) {
            $this->message['subject'] = 'Application Log';
        }
        $messages = array_map([$this, 'formatMessage'], $this->messages);
        $body = wordwrap(implode("\n", $messages), 70);
        $message = $this->composeMessage($body);
        if (!$message->send($this->mailer)) {
            throw new LogRuntimeException('Unable to export log through email!');
        }
    }

    /**
     * Composes a mail message with the given body content.
     * @param string $body the body content
     * @return MessageInterface $message
     */
    protected function composeMessage(string $body): MessageInterface
    {
        $message = $this->mailer->compose();
        AbstractContainer::configure($message, $this->message);
        $message->setTextBody($body);

        return $message;
    }
}
