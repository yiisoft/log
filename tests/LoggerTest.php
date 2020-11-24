<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests;

use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use stdClass;
use Yiisoft\Log\Logger;
use Yiisoft\Log\LogRuntimeException;
use Yiisoft\Log\Tests\TestAsset\DummyTarget;

use function array_pop;
use function memory_get_usage;

final class LoggerTest extends LoggerTestCase
{
    private Logger $logger;
    private DummyTarget $target;

    public function setUp(): void
    {
        $this->target = new DummyTarget();
        $this->logger = new Logger([DummyTarget::class => $this->target]);
    }

    public function testLogWithTraceLevel(): void
    {
        $memory = memory_get_usage();
        $this->logger->setTraceLevel($traceLevel = 3);
        $this->assertSame($traceLevel, $this->logger->getTraceLevel());

        $this->logger->log(LogLevel::INFO, 'test3');

        $messages = $this->getInaccessibleMessages($this->logger);
        $this->assertCount(1, $messages);
        $this->assertEquals(LogLevel::INFO, $messages[0][0]);
        $this->assertEquals('test3', $messages[0][1]);
        $this->assertEquals('application', $messages[0][2]['category']);
        $this->assertEquals([
            'file' => __FILE__,
            'line' => 34,
            'function' => 'log',
            'class' => Logger::class,
            'type' => '->',
        ], $messages[0][2]['trace'][0]);
        $this->assertCount(3, $messages[0][2]['trace']);
        $this->assertGreaterThanOrEqual($memory, $messages[0][2]['memory']);
    }

    public function testLog(): void
    {
        $memory = memory_get_usage();
        $this->logger->log(LogLevel::INFO, 'test1');

        $messages = $this->getInaccessibleMessages($this->logger);
        $this->assertCount(1, $messages);
        $this->assertEquals(LogLevel::INFO, $messages[0][0]);
        $this->assertEquals('test1', $messages[0][1]);
        $this->assertEquals('application', $messages[0][2]['category']);
        $this->assertEquals([], $messages[0][2]['trace']);
        $this->assertGreaterThanOrEqual($memory, $messages[0][2]['memory']);

        $this->logger->log(LogLevel::ERROR, 'test2', ['category' => 'category']);

        $messages = $this->getInaccessibleMessages($this->logger);
        $this->assertCount(2, $messages);
        $this->assertEquals(LogLevel::ERROR, $messages[1][0]);
        $this->assertEquals('test2', $messages[1][1]);
        $this->assertEquals('category', $messages[1][2]['category']);
        $this->assertEquals([], $messages[1][2]['trace']);
        $this->assertGreaterThanOrEqual($memory, $messages[1][2]['memory']);
    }

    public function testLogWithThrowableMessage(): void
    {
        $message = new LogRuntimeException('some error');
        $this->logger->log(LogLevel::ERROR, $message);

        $messages = $this->getInaccessibleMessages($this->logger);
        $this->assertInstanceOf(LogRuntimeException::class, $messages[0][2]['exception']);
        $this->assertSame($message, $messages[0][2]['exception']);
    }

    public function messageProvider(): array
    {
        return [
            'string' => ['test', 'test'],
            'int' => [1, '1'],
            'float' => [1.1, '1.1'],
            'bool' => [true, '1'],
            'callable' => [fn () => null, 'fn () => null'],
            'object' => [new stdClass(), 'unserialize(\'O:8:"stdClass":0:{}\')'],
            'stringable-object' => [
                $stringableObject = new class() {
                    public function __toString(): string
                    {
                        return 'Stringable object';
                    }
                },
                $stringableObject->__toString(),
            ],
        ];
    }

    /**
     * @dataProvider messageProvider
     *
     * @param $message
     * @param string $expected
     */
    public function testPsrLogInterfaceMethods($message, string $expected): void
    {
        $levels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];
        $this->logger->emergency($message);
        $this->logger->alert($message);
        $this->logger->critical($message);
        $this->logger->error($message);
        $this->logger->warning($message);
        $this->logger->notice($message);
        $this->logger->info($message);
        $this->logger->debug($message);
        $this->logger->log('InFo', $message);

        $messages = $this->getInaccessibleMessages($this->logger);

        for ($i = 0; $i < count($levels); $i++) {
            $this->assertSame($levels[$i], $messages[$i][0]);
            $this->assertSame($expected, $messages[$i][1]);
        }

        $this->assertSame(LogLevel::INFO, $messages[8][0]);
        $this->assertSame($expected, $messages[8][1]);
    }

    public function testSetExcludedTracePaths(): void
    {
        $this->logger->setTraceLevel(20);
        $this->logger->info('info message');

        $messages = $this->getInaccessibleMessages($this->logger);
        $this->assertEquals(__FILE__, $messages[0][2]['trace'][1]['file']);

        $this->logger->setExcludedTracePaths([__DIR__]);
        $this->logger->info('info message');
        $messages = $this->getInaccessibleMessages($this->logger);

        foreach ($messages[1][2]['trace'] as $trace) {
            $this->assertNotEquals(__FILE__, $trace['file']);
        }
    }

    public function invalidExcludedTracePathsProvider(): array
    {
        return [
            'int' => [[1]],
            'float' => [[1.1]],
            'array' => [[[]]],
            'bool' => [[true]],
            'callable' => [[fn () => null]],
            'object' => [[new stdClass()]],
        ];
    }

    /**
     * @dataProvider invalidExcludedTracePathsProvider
     *
     * @param mixed $list
     */
    public function testSetExcludedTracePathsThrowExceptionForNonStringList($list): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->logger->setExcludedTracePaths($list);
    }

    public function testLogWithFlush(): void
    {
        $this->logger->setFlushInterval($flushInterval = 1);
        $this->assertSame($flushInterval, $this->logger->getFlushInterval());

        $this->logger->log(LogLevel::INFO, 'test');

        $this->assertSame(0, $this->target->getExportCount());
        $this->assertSame('info', $this->target->getMessages()[0][0]);
        $this->assertSame('test', $this->target->getMessages()[0][1]);
    }

    public function testFlushWithDispatch(): void
    {
        $this->logger->info('test');
        $this->logger->flush();
        $this->assertSame(0, $this->target->getExportCount());
    }

    public function testFlushWithDispatchAndDefinedParam(): void
    {
        $this->logger->info('test');
        $this->logger->flush(true);
        $this->assertSame(1, $this->target->getExportCount());
    }

    public function testGetLevelName(): void
    {
        $this->assertEquals('info', Logger::getLevelName(LogLevel::INFO));
        $this->assertEquals('error', Logger::getLevelName(LogLevel::ERROR));
        $this->assertEquals('warning', Logger::getLevelName(LogLevel::WARNING));
        $this->assertEquals('debug', Logger::getLevelName(LogLevel::DEBUG));
        $this->assertEquals('emergency', Logger::getLevelName(LogLevel::EMERGENCY));
        $this->assertEquals('alert', Logger::getLevelName(LogLevel::ALERT));
        $this->assertEquals('critical', Logger::getLevelName(LogLevel::CRITICAL));
        $this->assertEquals('emergency', Logger::getLevelName('EmeRgeNcy'));
    }

    public function invalidMessageLevelProvider(): array
    {
        return [
            'string' => ['unknown'],
            'int' => [1],
            'float' => [1.1],
            'bool' => [true],
            'array' => [[]],
            'callable' => [fn () => null],
            'object' => [new stdClass()],
        ];
    }

    /**
     * @dataProvider invalidMessageLevelProvider
     *
     * @param mixed $level
     */
    public function testGetLevelNameThrowExceptionForInvalidMessageLevel($level): void
    {
        $this->expectException(InvalidArgumentException::class);
        Logger::getLevelName($level);
    }

    public function testSetTarget(): void
    {
        $this->assertEquals([DummyTarget::class => $this->target], $this->logger->getTargets());
        $this->assertSame($this->target, $this->logger->getTargets()[DummyTarget::class]);

        $logger = new Logger();
        $target = new DummyTarget();
        $logger->setTargets([$target]);
        $this->assertSame([$target], $logger->getTargets());
        $this->assertSame($target, $logger->getTargets()[0]);
        $this->assertSame($target, $logger->getTarget(0));
    }

    public function invalidTargetProvider(): array
    {
        return [
            'string' => [['a']],
            'int' => [[1]],
            'float' => [[1.1]],
            'bool' => [[true]],
            'array' => [[[]]],
            'callable' => [[fn () => null]],
            'object' => [[new stdClass()]],
        ];
    }

    /**
     * @dataProvider invalidTargetProvider
     *
     * @param mixed $target
     */
    public function testSetTargetThrowExceptionForNonInstanceTarget($target): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->logger->setTargets($target);
    }

    public function testAddTarget(): void
    {
        $logger = new Logger();

        $target = new DummyTarget();
        $logger->setTargets([$target]);

        $namedTarget = new DummyTarget();
        $logger->addTarget($namedTarget, 'test-target');

        $targets = $logger->getTargets();
        $this->assertCount(2, $targets);
        $this->assertTrue(isset($targets['test-target']));
        $this->assertSame($namedTarget, $targets['test-target']);

        $namelessTarget = new DummyTarget();
        $logger->addTarget($namelessTarget);
        $targets = $logger->getTargets();
        $this->assertCount(3, $targets);
        $this->assertSame($namelessTarget, array_pop($targets));
    }

    public function dataParseMessageProvider(): array
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
     * @dataProvider dataParseMessageProvider
     *
     * @param string $message
     * @param array $context
     * @param string $expected
     */
    public function testParseMessage(string $message, array $context, string $expected): void
    {
        $this->logger->log(LogLevel::INFO, $message, $context);
        $messages = $this->getInaccessibleMessages($this->logger);
        [, $message] = $messages[0];
        $this->assertEquals($expected, $message);
    }
}
