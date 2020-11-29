<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests;

use InvalidArgumentException;
use Psr\Log\LogLevel;
use stdClass;
use PHPUnit\Framework\TestCase;
use Yiisoft\Log\MessageCollection;

final class MessageCollectionTest extends TestCase
{
    private MessageCollection $messages;

    public function setUp(): void
    {
        $this->messages = new MessageCollection();
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
    public function testAdd($message, string $expected): void
    {
        $this->messages->add(LogLevel::INFO, $message, ['foo' => 'bar']);

        $this->assertSame(LogLevel::INFO, $this->messages->all()[0][0]);
        $this->assertSame($expected, $this->messages->all()[0][1]);
        $this->assertSame(['foo' => 'bar'], $this->messages->all()[0][2]);
    }

    /**
     * @dataProvider messageProvider
     *
     * @param mixed $message
     * @param string $expected
     */
    public function testAddMultiple($message, string $expected): void
    {
        $this->messages->addMultiple([[LogLevel::INFO, $message, ['foo' => 'bar']]]);

        $this->assertSame(LogLevel::INFO, $this->messages->all()[0][0]);
        $this->assertSame($expected, $this->messages->all()[0][1]);
        $this->assertSame(['foo' => 'bar'], $this->messages->all()[0][2]);
    }

    public function testAddMultipleWithMultipleMessages(): void
    {
        $messages = [
            [LogLevel::INFO, 'info', []],
            [LogLevel::WARNING, 'warning', ['foo' => 'bar']],
            [LogLevel::ERROR, 'error', ['foo' => 'bar', 'baz' => false]],
        ];

        $this->messages->addMultiple($messages);
        $this->assertSame($messages, $this->messages->all());
    }

    /**
     * @dataProvider invalidMessageLevelProvider
     *
     * @param mixed $level
     */
    public function testAddThrowExceptionForInvalidMessageLevel($level): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->messages->add($level, 'test', ['foo' => 'bar']);
    }

    public function invalidMessageStructureProvider(): array
    {
        return [
            'int' => [1],
            'float' => [1.1],
            'bool' => [true],
            'callable' => [fn () => null],
            'object' => [new stdClass()],
            'array-int' => [[1]],
            'array-float' => [[1.1]],
            'array-bool' => [[true]],
            'array-callable' => [[fn () => null]],
            'array-object' => [[new stdClass()]],
            'array-not-exist-index-0' => [['level' => 'info', 1 => 'message', 2 => []]],
            'array-not-exist-index-1' => [['level', 5 => 'message', 2 => []]],
            'array-not-exist-index-2' => [['level', 'message', 'context' => []]],
            'array-non-array-context' => [['level', 'message', 'context']],
        ];
    }

    /**
     * @dataProvider invalidMessageStructureProvider
     *
     * @param mixed $message
     */
    public function testAddMultipleThrowExceptionForInvalidMessageStructure($message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->messages->addMultiple([$message]);
    }

    /**
     * @dataProvider invalidMessageStructureProvider
     *
     * @param mixed $message
     */
    public function testCheckStructureThrowExceptionForInvalidMessageStructure($message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->messages->checkStructure($message);
    }

    public function testCount(): void
    {
        $messages = [
            [LogLevel::INFO, 'info', []],
            [LogLevel::WARNING, 'warning', ['foo' => 'bar']],
            [LogLevel::ERROR, 'error', ['foo' => 'bar', 'baz' => false]],
        ];

        $this->messages->addMultiple($messages);
        $this->assertCount($this->messages->count(), $this->messages->all());
    }

    public function testCLear(): void
    {
        $messages = [
            [LogLevel::INFO, 'info', []],
            [LogLevel::WARNING, 'warning', ['foo' => 'bar']],
            [LogLevel::ERROR, 'error', ['foo' => 'bar', 'baz' => false]],
        ];

        $this->messages->addMultiple($messages);
        $this->assertSame($messages, $this->messages->all());
        $this->messages->clear();
        $this->assertSame([], $this->messages->all());
    }

    public function testSetLevelsAndGetLevels(): void
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

        $this->messages->setLevels($levels);
        $this->assertSame($levels, $this->messages->getLevels());
    }

    public function invalidMessageLevelProvider(): array
    {
        return [
            'int' => [1],
            'float' => [1.1],
            'array' => [[]],
            'bool' => [true],
            'callable' => [fn () => null],
            'object' => [new stdClass()],
        ];
    }

    /**
     * @dataProvider invalidMessageLevelProvider
     *
     * @param mixed $level
     */
    public function testSetLevelsThrowExceptionForInvalidMessageLevel($level): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->messages->setLevels([$level]);
    }
}
