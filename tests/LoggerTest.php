<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use ReflectionClass;
use stdClass;
use RuntimeException;
use Yiisoft\Log\Logger;
use Yiisoft\Log\Message;
use Yiisoft\Log\Target;
use Yiisoft\Log\Tests\TestAsset\DummyTarget;

use function memory_get_usage;

final class LoggerTest extends TestCase
{
    private Logger $logger;
    private DummyTarget $target;

    public function setUp(): void
    {
        $this->target = new DummyTarget();
        $this->logger = new Logger([DummyTarget::class => $this->target]);
    }

    public function testLog(): void
    {
        $memory = memory_get_usage();
        $this->logger->log(LogLevel::INFO, 'test1');
        $messages = $this->getInaccessibleMessages($this->logger);

        $this->assertCount(1, $messages);
        $this->assertSame(LogLevel::INFO, $messages[0]->level());
        $this->assertSame('test1', $messages[0]->message());
        $this->assertSame('application', $messages[0]->context('category'));
        $this->assertSame([], $messages[0]->context('trace'));
        $this->assertGreaterThanOrEqual($memory, $messages[0]->context('memory'));

        $this->logger->log(LogLevel::ERROR, 'test2', ['category' => 'category']);
        $messages = $this->getInaccessibleMessages($this->logger);

        $this->assertCount(2, $messages);
        $this->assertSame(LogLevel::ERROR, $messages[1]->level());
        $this->assertSame('test2', $messages[1]->message());
        $this->assertSame('category', $messages[1]->context('category'));
        $this->assertSame([], $messages[1]->context('trace'));
        $this->assertGreaterThanOrEqual($memory, $messages[1]->context('memory'));
    }

    public function testLogWithTraceLevel(): void
    {
        $memory = memory_get_usage();
        $this->logger->setTraceLevel($traceLevel = 3);

        $this->logger->log(LogLevel::INFO, 'test3');
        $messages = $this->getInaccessibleMessages($this->logger);

        $this->assertCount(1, $messages);
        $this->assertSame(LogLevel::INFO, $messages[0]->level());
        $this->assertSame('test3', $messages[0]->message());
        $this->assertSame('application', $messages[0]->context('category'));
        $this->assertSame([
            'file' => __FILE__,
            'line' => 61,
            'function' => 'log',
            'class' => Logger::class,
            'type' => '->',
        ], $messages[0]->context('trace')[0]);
        $this->assertCount(3, $messages[0]->context('trace'));
        $this->assertGreaterThanOrEqual($memory, $messages[0]->context('memory'));
    }

    public function messageProvider(): array
    {
        return [
            'string' => ['test', 'test'],
            'stringable-object' => [
                $stringableObject = new class () {
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
        $this->logger->log(LogLevel::INFO, $message);

        $messages = $this->getInaccessibleMessages($this->logger);

        for ($i = 0, $levelsCount = count($levels); $i < $levelsCount; $i++) {
            $this->assertSame($levels[$i], $messages[$i]->level());
            $this->assertSame($expected, $messages[$i]->message());
        }

        $this->assertSame(LogLevel::INFO, $messages[8]->level());
        $this->assertSame($expected, $messages[8]->message());
    }

    public function testSetExcludedTracePaths(): void
    {
        $this->logger->setTraceLevel(20);
        $this->logger->info('info message');

        $messages = $this->getInaccessibleMessages($this->logger);
        $this->assertSame(__FILE__, $messages[0]->context('trace')[1]['file']);

        $this->logger->setExcludedTracePaths([__DIR__]);
        $this->logger->info('info message');
        $messages = $this->getInaccessibleMessages($this->logger);

        foreach ($messages[1]->context('trace') as $trace) {
            $this->assertNotSame(__FILE__, $trace['file']);
        }
    }

    public function invalidExcludedTracePathsProvider(): array
    {
        return [
            'int' => [[1]],
            'float' => [[1.1]],
            'array' => [[[]]],
            'bool' => [[true]],
            'null' => [[null]],
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

    public function testLevel(): void
    {
        $this->assertSame('info', Logger::validateLevel(LogLevel::INFO));
        $this->assertSame('error', Logger::validateLevel(LogLevel::ERROR));
        $this->assertSame('warning', Logger::validateLevel(LogLevel::WARNING));
        $this->assertSame('debug', Logger::validateLevel(LogLevel::DEBUG));
        $this->assertSame('emergency', Logger::validateLevel(LogLevel::EMERGENCY));
        $this->assertSame('alert', Logger::validateLevel(LogLevel::ALERT));
        $this->assertSame('critical', Logger::validateLevel(LogLevel::CRITICAL));
    }

    public function invalidMessageLevelProvider(): array
    {
        return [
            'string' => ['unknown'],
            'int' => [1],
            'float' => [1.1],
            'bool' => [true],
            'null' => [null],
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
        $this->expectException(\Psr\Log\InvalidArgumentException::class);
        Logger::validateLevel($level);
    }

    public function testSetTarget(): void
    {
        $this->assertSame([DummyTarget::class => $this->target], $this->logger->getTargets());
        $this->assertSame($this->target, $this->logger->getTargets()[DummyTarget::class]);

        $target = new DummyTarget();
        $logger = new Logger([$target]);
        $this->assertSame([$target], $logger->getTargets());
        $this->assertSame($target, $logger->getTargets()[0]);
    }

    public function invalidListTargetProvider(): array
    {
        return [
            'string' => [['a']],
            'int' => [[1]],
            'float' => [[1.1]],
            'bool' => [[true]],
            'null' => [[null]],
            'array' => [[[]]],
            'callable' => [[fn () => null]],
            'object' => [[new stdClass()]],
        ];
    }

    /**
     * @dataProvider invalidListTargetProvider
     *
     * @param array $targetList
     */
    public function testConstructorThrowExceptionForNonInstanceTarget(array $targetList): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Logger($targetList);
    }

    public function parseMessageProvider(): array
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
     * @dataProvider parseMessageProvider
     *
     * @param string $message
     * @param array $context
     * @param string $expected
     */
    public function testParseMessage(string $message, array $context, string $expected): void
    {
        $this->logger->log(LogLevel::INFO, $message, $context);
        $messages = $this->getInaccessibleMessages($this->logger);
        $this->assertSame($expected, $messages[0]->message());
    }

    public function testLogWithFlush(): void
    {
        $this->logger->setFlushInterval($flushInterval = 1);
        $this->target->setExportInterval($flushInterval);

        $this->logger->log(LogLevel::INFO, 'test');
        $this->assertSame(1, $this->target->getExportCount());
        $this->assertSame('info', $this->target->getExportMessages()[0]->level());
        $this->assertSame('test', $this->target->getExportMessages()[0]->message());
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

    /**
     * @covers \Yiisoft\Log\Logger::dispatch()
     */
    public function testDispatchWithDisabledTarget(): void
    {
        /** @var MockObject|Target $target */
        $target = $this
            ->getMockBuilder(Target::class)
            ->onlyMethods(['collect'])
            ->getMockForAbstractClass();

        $target
            ->expects($this->never())
            ->method($this->anything());
        $target->disable();

        $logger = new Logger(['fakeTarget' => $target]);
        $this->setInaccessibleMessages($logger, [new Message(LogLevel::INFO, 'test', [])]);
        $logger->flush(true);
    }

    /**
     * @covers \Yiisoft\Log\Logger::dispatch()
     */
    public function testDispatchWithSuccessTargetCollect(): void
    {
        $message = new Message(LogLevel::INFO, 'test', ['foo' => 'bar']);

        /** @var MockObject|Target $target */
        $target = $this
            ->getMockBuilder(Target::class)
            ->onlyMethods(['collect'])
            ->getMockForAbstractClass();

        $target
            ->expects($this->once())
            ->method('collect')
            ->with(
                $this->equalTo([$message]),
                $this->equalTo(true)
            );

        $logger = new Logger(['fakeTarget' => $target]);

        $this->setInaccessibleMessages($logger, [$message]);
        $logger->flush(true);
    }

    /**
     * @covers \Yiisoft\Log\Logger::dispatch()
     */
    public function testDispatchWithFakeTarget2ThrowExceptionWhenCollect(): void
    {
        $exception = new RuntimeException('some error');
        $message = new Message(LogLevel::INFO, 'test', ['foo' => 'bar']);

        /** @var MockObject|Target $target */
        $target1 = $this
            ->getMockBuilder(Target::class)
            ->onlyMethods(['collect'])
            ->getMockForAbstractClass();

        /** @var MockObject|Target $target */
        $target2 = $this
            ->getMockBuilder(Target::class)
            ->onlyMethods(['collect'])
            ->getMockForAbstractClass();

        $target1
            ->expects($this->exactly(2))
            ->method('collect')
            ->withConsecutive(
                [$this->equalTo([$message]), $this->equalTo(true)],
                [
                    $this->callback(function ($messages) use ($target1, $exception) {
                        $message = $messages[0] ?? null;
                        $text = 'Unable to send log via ' . get_class($target1) . ': RuntimeException: some error';
                        return (count($messages) === 1 && $message instanceof Message)
                            && $message->level() === LogLevel::WARNING
                            && $message->message() === $text
                            && is_float($message->context('time'))
                            && $message->context('exception') === $exception;
                    }),
                    $this->equalTo(true),
                ]
            );

        $target2
            ->expects($this->once())
            ->method('collect')
            ->with(
                $this->equalTo([$message]),
                $this->equalTo(true)
            )
            ->will($this->throwException($exception));

        $logger = new Logger([
            'fakeTarget1' => $target1,
            'fakeTarget2' => $target2,
        ]);

        $this->setInaccessibleMessages($logger, [$message]);
        $logger->flush(true);
    }

    /**
     * Sets an inaccessible object property to a designated value.
     *
     * @param Logger $logger
     * @param Message[] $messages
     * @param bool $revoke whether to make property inaccessible after setting.
     */
    private function setInaccessibleMessages(Logger $logger, array $messages, bool $revoke = true): void
    {
        $class = new ReflectionClass($logger);
        $property = $class->getProperty('messages');
        $property->setAccessible(true);
        $property->setValue($logger, $messages);

        if ($revoke) {
            $property->setAccessible(false);
        }
    }

    /**
     * Gets an inaccessible object property.
     *
     * @param Logger $logger
     * @param bool $revoke whether to make property inaccessible after getting.
     *
     * @return Message[]
     */
    private function getInaccessibleMessages(Logger $logger, bool $revoke = true): array
    {
        $class = new ReflectionClass($logger);
        $property = $class->getProperty('messages');
        $property->setAccessible(true);
        $messages = $property->getValue($logger);

        if ($revoke) {
            $property->setAccessible(false);
        }

        return $messages;
    }
}
