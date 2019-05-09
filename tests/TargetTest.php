<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Log\Tests;

use Psr\Log\LogLevel;
use Yiisoft\Log\Logger;
use Yiisoft\Log\Target;
use PHPUnit\Framework\TestCase;

/**
 * @group log
 */
class TargetTest extends TestCase
{
    public static $messages;

    public function filters()
    {
        return [
            [[], ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I']],
            [['levels' => []], ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I']],
            [
                ['levels' => [LogLevel::INFO, LogLevel::WARNING, LogLevel::ERROR, LogLevel::DEBUG]],
                ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
            ],
            [['levels' => ['error']], ['B', 'G', 'H', 'I']],
            [['levels' => [LogLevel::ERROR]], ['B', 'G', 'H', 'I']],
            [['levels' => ['error', 'warning']], ['B', 'C', 'G', 'H', 'I']],
            [['levels' => [LogLevel::ERROR, LogLevel::WARNING]], ['B', 'C', 'G', 'H', 'I']],

            [['categories' => ['application']], ['A', 'B', 'C', 'D', 'E']],
            [['categories' => ['application*']], ['A', 'B', 'C', 'D', 'E', 'F']],
            [['categories' => ['application.*']], ['F']],
            [['categories' => ['application.components']], []],
            [['categories' => ['application.components.Test']], ['F']],
            [['categories' => ['application.components.*']], ['F']],
            [['categories' => ['application.*', 'Yiisoft.Db.*']], ['F', 'G', 'H']],
            [['categories' => ['application.*', 'Yiisoft.Db.*'], 'except' => ['Yiisoft.Db.Command.*', 'Yiisoft\Db\*']], ['F', 'G']],
            [['except' => ['Yiisoft\Db\*']], ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']],
            [['categories' => ['Yiisoft*'], 'except' => ['Yiisoft\Db\*']], ['G', 'H']],

            [['categories' => ['application', 'Yiisoft.Db.*'], 'levels' => [LogLevel::ERROR]], ['B', 'G', 'H']],
            [['categories' => ['application'], 'levels' => [LogLevel::ERROR]], ['B']],
            [['categories' => ['application'], 'levels' => [LogLevel::ERROR, LogLevel::WARNING]], ['B', 'C']],
        ];
    }

    /**
     * @dataProvider filters
     * @param array $filter
     * @param array $expected
     */
    public function testFilter($filter, $expected)
    {
        static::$messages = [];

        $filter = array_merge($filter, ['logVars' => []]);
        $target = new TestTarget();
        foreach ($filter as $key => $value) {
            $target->{'set' . ucfirst($key)}($value);
        }
        $logger = new Logger(['test' => $target]);

        $logger->setFlushInterval(1);
        $logger->log(LogLevel::INFO, 'testA');
        $logger->log(LogLevel::ERROR, 'testB');
        $logger->log(LogLevel::WARNING, 'testC');
        $logger->log(LogLevel::DEBUG, 'testD');
        $logger->log(LogLevel::INFO, 'testE', ['category' => 'application']);
        $logger->log(LogLevel::INFO, 'testF', ['category' => 'application.components.Test']);
        $logger->log(LogLevel::ERROR, 'testG', ['category' => 'Yiisoft.Db.Command']);
        $logger->log(LogLevel::ERROR, 'testH', ['category' => 'Yiisoft.Db.Command.whatever']);
        $logger->log(LogLevel::ERROR, 'testI', ['category' => 'Yiisoft\Db\Command::query']);

        $this->assertCount(count($expected), static::$messages, 'Expected ' . implode(',', $expected) . ', got ' . implode(',', array_column(static::$messages, 0)));
        $i = 0;
        foreach ($expected as $e) {
            $this->assertEquals('test' . $e, static::$messages[$i++][1]);
        }
    }

    public function testGetContextMessage()
    {
        $target = new TestTarget();
        $target->setLogVars([
            'A', '!A.A_b', 'A.A_d',
            'B.B_a',
            'C', 'C.C_a',
        ]);
        $GLOBALS['A'] = [
            'A_a' => 1,
            'A_b' => 1,
            'A_c' => 1,
        ];
        $GLOBALS['B'] = [
            'B_a' => 1,
            'B_b' => 1,
            'B_c' => 1,
        ];
        $GLOBALS['C'] = [
            'C_a' => 1,
            'C_b' => 1,
            'C_c' => 1,
        ];
        $GLOBALS['E'] = [
            'C_a' => 1,
            'C_b' => 1,
            'C_c' => 1,
        ];
        $context = $target->getContextMessage();
        $this->assertContains('A_a', $context);
        $this->assertNotContains('A_b', $context);
        $this->assertContains('A_c', $context);
        $this->assertContains('B_a', $context);
        $this->assertNotContains('B_b', $context);
        $this->assertNotContains('B_c', $context);
        $this->assertContains('C_a', $context);
        $this->assertContains('C_b', $context);
        $this->assertContains('C_c', $context);
        $this->assertNotContains('D_a', $context);
        $this->assertNotContains('D_b', $context);
        $this->assertNotContains('D_c', $context);
        $this->assertNotContains('E_a', $context);
        $this->assertNotContains('E_b', $context);
        $this->assertNotContains('E_c', $context);
    }

    public function testGetEnabled()
    {
        /** @var Target $target */
        $target = $this->getMockForAbstractClass(Target::class);

        $target->enable();
        $this->assertTrue($target->isEnabled());

        $target->disable();
        $this->assertFalse($target->isEnabled());

        $target->setEnabled(static function ($target) {
            return empty($target->messages);
        });
        $this->assertTrue($target->isEnabled());
    }

    public function testFormatMessage()
    {
        /** @var Target $target */
        $target = $this->getMockForAbstractClass(Target::class);

        $text = 'message';
        $level = LogLevel::INFO;
        $category = 'application';
        $timestamp = 1508160390.6083;

        $expectedWithoutMicro = '2017-10-16 13:26:30 [info][application] message';
        $formatted = $target->formatMessage([$level, $text, ['category' => $category, 'time' => $timestamp]]);
        $this->assertSame($expectedWithoutMicro, $formatted);

        $target->setMicrotime(true);

        $expectedWithMicro = '2017-10-16 13:26:30.6083 [info][application] message';
        $formatted = $target->formatMessage([$level, $text, ['category' => $category, 'time' => $timestamp]]);
        $this->assertSame($expectedWithMicro, $formatted);

        $timestamp = 1508160390;

        $expectedWithoutMicro = '2017-10-16 13:26:30 [info][application] message';
        $formatted = $target->formatMessage([$level, $text, ['category' => $category, 'time' => $timestamp]]);
        $this->assertSame($expectedWithoutMicro, $formatted);
    }
}

class TestTarget extends Target
{
    public function __construct()
    {
        $this->setExportInterval(1);
    }

    /**
     * Exports log [[messages]] to a specific destination.
     * Child classes must implement this method.
     */
    public function export(): void
    {
        TargetTest::$messages = array_merge(TargetTest::$messages, $this->getMessages());
        $this->setMessages([]);
    }

    /**
     * {@inheritdoc}
     */
    public function getContextMessage(): string
    {
        return parent::getContextMessage();
    }
}
