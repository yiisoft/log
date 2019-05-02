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
class LoggerTest extends TestCase
{
    /**
     * @var Logger|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $logger;

    protected function setUp()
    {
        $this->logger = $this->getMockBuilder('Yiisoft\Log\Logger')
            ->setMethods(['dispatch'])
            ->getMock();
    }

    /**
     * @covers \Yiisoft\Log\Logger::Log()
     */
    public function testLog()
    {
        $memory = memory_get_usage();
        $this->logger->log(LogLevel::INFO, 'test1');
        $this->assertCount(1, $this->logger->messages);
        $this->assertEquals(LogLevel::INFO, $this->logger->messages[0][0]);
        $this->assertEquals('test1', $this->logger->messages[0][1]);
        $this->assertEquals('application', $this->logger->messages[0][2]['category']);
        $this->assertEquals([], $this->logger->messages[0][2]['trace']);
        $this->assertGreaterThanOrEqual($memory, $this->logger->messages[0][2]['memory']);

        $this->logger->log(LogLevel::ERROR, 'test2', ['category' => 'category']);
        $this->assertCount(2, $this->logger->messages);
        $this->assertEquals(LogLevel::ERROR, $this->logger->messages[1][0]);
        $this->assertEquals('test2', $this->logger->messages[1][1]);
        $this->assertEquals('category', $this->logger->messages[1][2]['category']);
        $this->assertEquals([], $this->logger->messages[1][2]['trace']);
        $this->assertGreaterThanOrEqual($memory, $this->logger->messages[1][2]['memory']);
    }

    /**
     * @covers \Yiisoft\Log\Logger::Log()
     */
    public function testLogWithTraceLevel()
    {
        $memory = memory_get_usage();
        $this->logger->setTraceLevel(3);
        $this->logger->log(LogLevel::INFO, 'test3');
        $this->assertCount(1, $this->logger->messages);
        $this->assertEquals(LogLevel::INFO, $this->logger->messages[0][0]);
        $this->assertEquals('test3', $this->logger->messages[0][1]);
        $this->assertEquals('application', $this->logger->messages[0][2]['category']);
        $this->assertEquals([
            'file' => __FILE__,
            'line' => 62,
            'function' => 'log',
            'class' => Logger::class,
            'type' => '->',
        ], $this->logger->messages[0][2]['trace'][0]);
        $this->assertCount(3, $this->logger->messages[0][2]['trace']);
        $this->assertGreaterThanOrEqual($memory, $this->logger->messages[0][2]['memory']);
    }

    /**
     * @covers \Yiisoft\Log\Logger::Log()
     */
    public function testLogWithFlush()
    {
        /* @var $logger Logger|\PHPUnit_Framework_MockObject_MockObject */
        $logger = $this->getMockBuilder(Logger::class)
            ->setMethods(['flush'])
            ->getMock();
        $logger->setFlushInterval(1);
        $logger->expects($this->exactly(1))->method('flush');
        $logger->log(LogLevel::INFO, 'test1');
    }

    /**
     * @covers \Yiisoft\Log\Logger::Flush()
     */
    public function testFlushWithDispatch()
    {
        $message = ['anything'];
        $this->logger->expects($this->once())
            ->method('dispatch')->with($this->equalTo($message), $this->equalTo(false));

        $this->logger->messages = $message;
        $this->logger->flush();
        $this->assertEmpty($this->logger->messages);
    }

    /**
     * @covers \Yiisoft\Log\Logger::Flush()
     */
    public function testFlushWithDispatchAndDefinedParam()
    {
        $message = ['anything'];
        $this->logger->expects($this->once())
            ->method('dispatch')->with($this->equalTo($message), $this->equalTo(true));

        $this->logger->messages = $message;
        $this->logger->flush(true);
        $this->assertEmpty($this->logger->messages);
    }

    /**
     * @covers \Yiisoft\Log\Logger::getElapsedTime()
     */
    public function testGetElapsedTime()
    {
        $timeBefore = \microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        usleep(1);
        $actual = $this->logger->getElapsedTime();
        usleep(1);
        $timeAfter = \microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];

        $this->assertGreaterThan($timeBefore, $actual);
        $this->assertLessThan($timeAfter, $actual);
    }

    /**
     * @covers \Yiisoft\Log\Logger::getLevelName()
     */
    public function testGetLevelName()
    {
        $this->assertEquals('info', Logger::getLevelName(LogLevel::INFO));
        $this->assertEquals('error', Logger::getLevelName(LogLevel::ERROR));
        $this->assertEquals('warning', Logger::getLevelName(LogLevel::WARNING));
        $this->assertEquals('debug', Logger::getLevelName(LogLevel::DEBUG));
        $this->assertEquals('emergency', Logger::getLevelName(LogLevel::EMERGENCY));
        $this->assertEquals('alert', Logger::getLevelName(LogLevel::ALERT));
        $this->assertEquals('critical', Logger::getLevelName(LogLevel::CRITICAL));
        $this->assertEquals('unknown', Logger::getLevelName(0));
    }

    /**
     * @covers \Yiisoft\Log\Logger::setTargets()
     * @covers \Yiisoft\Log\Logger::getTargets()
     */
    public function testSetupTarget()
    {
        $logger = new Logger();

        $target = $this->getMockForAbstractClass(Target::class);
        $logger->setTargets([$target]);

        $this->assertEquals([$target], $logger->getTargets());
        $this->assertSame($target, $logger->getTargets()[0]);
    }

    /**
     * @depends testSetupTarget
     *
     * @covers \Yiisoft\Log\Logger::addTarget()
     */
    public function testAddTarget()
    {
        $logger = new Logger();

        $target = $this->getMockForAbstractClass(Target::class);
        $logger->setTargets([$target]);

        $namedTarget = $this->getMockForAbstractClass(Target::class);
        $logger->addTarget($namedTarget, 'test-target');

        $targets = $logger->getTargets();
        $this->assertCount(2, $targets);
        $this->assertTrue(isset($targets['test-target']));
        $this->assertSame($namedTarget, $targets['test-target']);

        $namelessTarget = $this->getMockForAbstractClass(Target::class);
        $logger->addTarget($namelessTarget);
        $targets = $logger->getTargets();
        $this->assertCount(3, $targets);
        $this->assertSame($namelessTarget, array_pop($targets));
    }

    /**
     * Data provider for [[testParseMessage()]]
     * @return array test data.
     */
    public function dataProviderParseMessage()
    {
        return [
            [
                'no placeholder',
                ['foo' => 'some'],
                'no placeholder',
            ],
            [
                'has {foo} placeholder',
                ['foo' => 'some'],
                'has some placeholder',
            ],
            [
                'has {foo} placeholder',
                [],
                'has {foo} placeholder',
            ],
        ];
    }

    /**
     * @depends testLog
     * @dataProvider dataProviderParseMessage
     *
     * @covers \Yiisoft\Log\Logger::parseMessage()
     *
     * @param $message
     * @param array $context
     * @param $expected
     */
    public function testParseMessage($message, array $context, $expected)
    {
        $this->logger->log(LogLevel::INFO, $message, $context);
        [$level, $message, $context] = $this->logger->messages[0];
        $this->assertEquals($expected, $message);
    }
}
