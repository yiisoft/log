<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests;

use Psr\Log\LogLevel;
use Yiisoft\Log\Logger;
use Yiisoft\Log\Target;

/**
 * @group log
 */
class LoggerTest extends TestCase
{
    /**
     * @var Logger|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $logger;

    protected function setUp(): void
    {
        $this->logger = $this->getMockBuilder(Logger::class)
            ->onlyMethods(['dispatch'])
            ->getMock();
    }

    /**
     * @covers \Yiisoft\Log\Logger::Log()
     */
    public function testLogWithTraceLevel(): void
    {
        $memory = memory_get_usage();
        $this->logger->setTraceLevel(3);
        $this->logger->log(LogLevel::INFO, 'test3');

        $messages = $this->getInaccessibleProperty($this->logger, 'messages');
        $this->assertCount(1, $messages);
        $this->assertEquals(LogLevel::INFO, $messages[0][0]);
        $this->assertEquals('test3', $messages[0][1]);
        $this->assertEquals('application', $messages[0][2]['category']);
        $this->assertEquals([
            'file' => __FILE__,
            'line' => 35,
            'function' => 'log',
            'class' => Logger::class,
            'type' => '->',
        ], $messages[0][2]['trace'][0]);
        $this->assertCount(3, $messages[0][2]['trace']);
        $this->assertGreaterThanOrEqual($memory, $messages[0][2]['memory']);
    }

    /**
     * @covers \Yiisoft\Log\Logger::Log()
     */
    public function testLog(): void
    {
        $memory = memory_get_usage();
        $this->logger->log(LogLevel::INFO, 'test1');

        $messages = $this->getInaccessibleProperty($this->logger, 'messages');
        $this->assertCount(1, $messages);
        $this->assertEquals(LogLevel::INFO, $messages[0][0]);
        $this->assertEquals('test1', $messages[0][1]);
        $this->assertEquals('application', $messages[0][2]['category']);
        $this->assertEquals([], $messages[0][2]['trace']);
        $this->assertGreaterThanOrEqual($memory, $messages[0][2]['memory']);

        $this->logger->log(LogLevel::ERROR, 'test2', ['category' => 'category']);

        $messages = $this->getInaccessibleProperty($this->logger, 'messages');
        $this->assertCount(2, $messages);
        $this->assertEquals(LogLevel::ERROR, $messages[1][0]);
        $this->assertEquals('test2', $messages[1][1]);
        $this->assertEquals('category', $messages[1][2]['category']);
        $this->assertEquals([], $messages[1][2]['trace']);
        $this->assertGreaterThanOrEqual($memory, $messages[1][2]['memory']);
    }

    public function testExcludedTracePaths(): void
    {
        $this->logger->setTraceLevel(20);

        $this->logger->info('info message');

        $messages = $this->getInaccessibleProperty($this->logger, 'messages');
        $this->assertEquals(__FILE__, $messages[0][2]['trace'][1]['file']);

        $this->logger->setExcludedTracePaths([__DIR__]);
        $this->logger->info('info message');
        $messages = $this->getInaccessibleProperty($this->logger, 'messages');
        foreach ($messages[1][2]['trace'] as $trace) {
            $this->assertNotEquals(__FILE__, $trace['file']);
        }
    }

    /**
     * @covers \Yiisoft\Log\Logger::Log()
     */
    public function testLogWithFlush(): void
    {
        /* @var $logger Logger|\PHPUnit_Framework_MockObject_MockObject */
        $logger = $this->getMockBuilder(Logger::class)
            ->onlyMethods(['flush'])
            ->getMock();
        $logger->setFlushInterval(1);
        $logger->expects($this->once())->method('flush');
        $logger->log(LogLevel::INFO, 'test1');
    }

    /**
     * @covers \Yiisoft\Log\Logger::Flush()
     */
    public function testFlushWithDispatch(): void
    {
        $message = ['anything'];
        $this->logger->expects($this->once())
            ->method('dispatch')->with($this->equalTo($message), $this->equalTo(false));

        $this->setInaccessibleProperty($this->logger, 'messages', $message);
        $this->logger->flush();
        $this->assertEmpty($this->getInaccessibleProperty($this->logger, 'messages'));
    }

    /**
     * @covers \Yiisoft\Log\Logger::Flush()
     */
    public function testFlushWithDispatchAndDefinedParam(): void
    {
        $message = ['anything'];
        $this->logger->expects($this->once())
            ->method('dispatch')->with($this->equalTo($message), $this->equalTo(true));

        $this->setInaccessibleProperty($this->logger, 'messages', $message);
        $this->logger->flush(true);
        $this->assertEmpty($this->getInaccessibleProperty($this->logger, 'messages'));
    }

    /**
     * @covers \Yiisoft\Log\Logger::getLevelName()
     */
    public function testGetLevelName(): void
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
    public function testSetupTarget(): void
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
    public function testAddTarget(): void
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
     *
     * @return array test data.
     */
    public function dataProviderParseMessage(): array
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
     * @param string $message
     * @param array $context
     * @param string $expected
     */
    public function testParseMessage(string $message, array $context, string $expected): void
    {
        $this->logger->log(LogLevel::INFO, $message, $context);
        $messages = $this->getInaccessibleProperty($this->logger, 'messages');
        [, $message] = $messages[0];
        $this->assertEquals($expected, $message);
    }
}
