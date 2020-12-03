<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests;

use Exception;
use InvalidArgumentException;
use Psr\Log\LogLevel;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Yiisoft\Log\Logger;
use Yiisoft\Log\Message;
use Yiisoft\Log\Tests\TestAsset\DummyTarget;

use function array_map;
use function array_merge;
use function json_encode;
use function implode;
use function strtoupper;
use function ucfirst;

final class TargetTest extends TestCase
{
    private DummyTarget $target;

    public function setUp(): void
    {
        $this->target = new DummyTarget();
    }

    public function filterProvider(): array
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
            [
                [
                    'categories' => ['application.*', 'Yiisoft.Db.*'],
                    'except' => ['Yiisoft.Db.Command.*', 'Yiisoft\Db\*'],
                ],
                ['F', 'G'],
            ],
            [['except' => ['Yiisoft\Db\*']], ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']],
            [['categories' => ['Yiisoft*'], 'except' => ['Yiisoft\Db\*']], ['G', 'H']],

            [['categories' => ['application', 'Yiisoft.Db.*'], 'levels' => [LogLevel::ERROR]], ['B', 'G', 'H']],
            [['categories' => ['application'], 'levels' => [LogLevel::ERROR]], ['B']],
            [['categories' => ['application'], 'levels' => [LogLevel::ERROR, LogLevel::WARNING]], ['B', 'C']],
        ];
    }

    /**
     * @dataProvider filterProvider
     *
     * @param array $filter
     * @param array $expected
     */
    public function testFilter(array $filter, array $expected): void
    {
        $filter = array_merge($filter, ['commonContext' => []]);

        foreach ($filter as $key => $value) {
            $this->target->{'set' . ucfirst($key)}($value);
        }
        $logger = new Logger([DummyTarget::class => $this->target]);

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

        $messages = $this->target->getMessages();
        $texts = array_map(fn (Message $message): string => $message->message(), $messages);

        $this->assertCount(
            count($expected),
            $messages,
            'Expected ' . implode(',', $expected) . ', got ' . implode(',', $texts)
        );

        $i = 0;
        foreach ($expected as $e) {
            $this->assertSame('test' . $e, $messages[$i++]->message());
        }
    }

    public function testEnabled(): void
    {
        $this->target->enable();
        $this->assertTrue($this->target->isEnabled());

        $this->target->disable();
        $this->assertFalse($this->target->isEnabled());

        $enabled = true;
        $this->target->setEnabled(static fn () => $enabled);
        $this->assertTrue($this->target->isEnabled());
    }

    public function invalidCallableEnabledProvider(): array
    {
        return [
            'string' => [fn () => 'a'],
            'int' => [fn () => 1],
            'float' => [fn () => 1.1],
            'array' => [fn () => []],
            'callable' => [fn () => fn () => true],
            'object' => [fn () => new stdClass()],
        ];
    }

    /**
     * @dataProvider invalidCallableEnabledProvider
     *
     * @param mixed $value
     */
    public function testIsEnabledThrowExceptionForCallableReturnNotBoolean(callable $value): void
    {
        $this->target->setEnabled($value);
        $this->expectException(RuntimeException::class);
        $this->target->isEnabled();
    }

    public function testFormatAndFormatTimestamp(): void
    {
        $text = 'message';
        $level = LogLevel::INFO;
        $category = 'application';
        $timestamp = 1508160390.6083;
        $context = "\n\nMessage context:\n\ncategory: '{$category}'\ntime: {$timestamp}\n";

        $this->target->setTimestampFormat('Y-m-d H:i:s');

        $expectedWithoutMicro = "2017-10-16 13:26:30 [info][application] message{$context}";
        $this->collectOneAndExport($level, $text, ['category' => $category, 'time' => $timestamp]);
        $this->assertSame($expectedWithoutMicro, $this->target->formatMessages());

        $this->target->setTimestampFormat('Y-m-d H:i:s.u');

        $expectedWithMicro = "2017-10-16 13:26:30.608300 [info][application] message{$context}";
        $this->collectOneAndExport($level, $text, ['category' => $category, 'time' => $timestamp]);
        $this->assertSame($expectedWithMicro, $this->target->formatMessages());

        $timestamp = 1508160390;
        $this->target->setTimestampFormat('Y-m-d H:i:s');
        $context = "\n\nMessage context:\n\ncategory: '{$category}'\ntime: {$timestamp}\n";

        $expectedWithoutMicro = "2017-10-16 13:26:30 [info][application] message{$context}";
        $this->collectOneAndExport($level, $text, ['category' => $category, 'time' => $timestamp]);
        $this->assertSame($expectedWithoutMicro, $this->target->formatMessages());

        $this->target->setTimestampFormat('D d F Y');

        $expectedCustom = "Mon 16 October 2017 [info][application] message{$context}";
        $this->collectOneAndExport($level, $text, ['category' => $category, 'time' => $timestamp]);
        $this->assertSame($expectedCustom, $this->target->formatMessages());
    }

    public function testFormatTimestampWithoutContextParameters(): void
    {
        $text = 'message';
        $level = LogLevel::INFO;
        $category = 'application';
        $timestamp = 1508160390.6083;

        $this->target->setTimestampFormat('Y-m-d H:i:s.u');

        $expectedWithMicro = '2017-10-16 13:26:30.608300 [info][application] message'
            . "\n\nMessage context:\n\ntime: {$timestamp}\n";
        $this->collectOneAndExport($level, $text, ['time' => $timestamp]);
        $this->assertSame($expectedWithMicro, $this->target->formatMessages());

        $this->target->setTimestampFormat('Y-m-d H:i:s');
        $expectedPatternWithoutMicro = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \[info\]\[application\] message/';

        $this->collectOneAndExport($level, $text, ['category' => $category]);
        $this->assertMatchesRegularExpression($expectedPatternWithoutMicro, $this->target->formatMessages());

        $this->collectOneAndExport($level, $text);
        $this->assertMatchesRegularExpression($expectedPatternWithoutMicro, $this->target->formatMessages());
    }

    public function testFormatMessagesWithTraceInContext(): void
    {
        $timestamp = 1508160390;
        $this->target->setTimestampFormat('Y-m-d H:i:s');
        $this->collectOneAndExport(
            LogLevel::INFO,
            'message',
            ['category' => 'app', 'time' => $timestamp, 'trace' => [['file' => '/path/to/file', 'line' => 99]]],
        );

        $expected = "2017-10-16 13:26:30 [info][app] message\n\nMessage context:\n\n"
            . "trace:\n    in /path/to/file:99\n"
            . "category: 'app'\ntime: {$timestamp}\n";

        $this->assertSame($expected, $this->target->formatMessages());
    }

    public function invalidStringListProvider(): array
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
     * @dataProvider invalidStringListProvider
     *
     * @param array $list
     */
    public function testSetCategoriesThrowExceptionForNonStringList(array $list): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->target->setCategories($list);
    }

    /**
     * @dataProvider invalidStringListProvider
     *
     * @param array $list
     */
    public function testSetExceptThrowExceptionForNonStringList(array $list): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->target->setExcept($list);
    }

    /**
     * @dataProvider invalidStringListProvider
     *
     * @param array $list
     */
    public function testSetLevelsThrowExceptionForNonStringList(array $list): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->target->setLevels($list);
    }

    public function testSetFormat(): void
    {
        $this->target->setFormat(static function (Message $message) {
            return "[{$message->level()}][{$message->context('category')}] {$message->message()}";
        });

        $expected = '[info][app] message';
        $this->collectOneAndExport(LogLevel::INFO, 'message', ['category' => 'app']);
        $this->assertSame($expected, $this->target->formatMessages());
    }

    public function testSetPrefix(): void
    {
        $this->target->setPrefix(static fn () => 'Prefix: ');
        $expected = '2017-10-16 13:26:30.608300 Prefix: [info][app] message'
            . "\n\nMessage context:\n\ncategory: 'app'\ntime: 1508160390.6083\n";
        $this->collectOneAndExport(LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1508160390.6083]);
        $this->assertSame($expected, $this->target->formatMessages());
    }

    public function testSetFormatAndSetPrefix(): void
    {
        $this->target->setFormat(static fn (Message $message) => "({$message->level()}) {$message->message()}");
        $this->target->setPrefix(static fn (Message $message) => strtoupper($message->context('category') . ': '));

        $expected = 'APP: (info) message';
        $this->collectOneAndExport(LogLevel::INFO, 'message', ['category' => 'app']);
        $this->assertSame($expected, $this->target->formatMessages());
    }

    public function collectMessageProvider(): array
    {
        return [
            'export' => [
                [
                    new Message(LogLevel::INFO, 'message-1', ['category' => 'app']),
                    new Message(LogLevel::DEBUG, 'message-2', ['category' => 'app']),
                ],
                true,
            ],
            'not-export' => [
                [
                    new Message(LogLevel::INFO, 'message-1', ['category' => 'app']),
                    new Message(LogLevel::DEBUG, 'message-2', ['category' => 'app']),
                ],
                false,
            ],
        ];
    }

    /**
     * @dataProvider collectMessageProvider
     *
     * @param array $messages
     * @param bool $export
     */
    public function testFormatMessagesWithSeparatorAndSetFormatAndSetPrefix(array $messages, bool $export): void
    {
        $this->target->setFormat(static fn (Message $message) => "({$message->level()}) {$message->message()}");
        $this->target->setPrefix(static fn (Message $message) => strtoupper($message->context('category') . ': '));

        $expected = "APP: (info) message-1\nAPP: (debug) message-2\n";
        $this->target->collect($messages, $export);

        $this->assertSame($expected, $this->target->formatMessages("\n"));
    }

    /**
     * @dataProvider collectMessageProvider
     *
     * @param array $messages
     * @param bool $export
     */
    public function testGetFormattedMessagesAndSetFormatAndSetPrefix(array $messages, bool $export): void
    {
        $this->target->setFormat(static fn (Message $message) => "({$message->level()}) {$message->message()}");
        $this->target->setPrefix(static fn (Message $message) => strtoupper($message->context('category') . ': '));

        $expected = ['APP: (info) message-1', 'APP: (debug) message-2'];
        $this->target->collect($messages, $export);

        $this->assertSame($expected, $this->target->getFormattedMessages());
    }

    /**
     * @dataProvider collectMessageProvider
     *
     * @param array $messages
     * @param bool $export
     */
    public function testSetExportIntervalAndSetFormat(array $messages, bool $export): void
    {
        $this->target->setExportInterval(3);
        $this->target->setFormat(static function (Message $message) {
            return "[{$message->level()}][{$message->context('category')}] {$message->message()}";
        });
        $this->target->collect($messages, $export);

        $this->assertSame((int) $export, $this->target->getExportCount());
    }

    public function contextProvider(): array
    {
        return [
            'string' => [['foo' => 'a'], "foo: 'a'"],
            'int' => [['foo' => 1], 'foo: 1'],
            'float' => [['foo' => 1.1], 'foo: 1.1'],
            'array' => [['foo' => []], 'foo: []'],
            'null' => [['foo' => null], 'foo: null'],
            'callable' => [['foo' => fn () => null], 'foo: fn () => null'],
            'exception' => [['foo' => $exception = new Exception('some error')], "foo: {$exception->__toString()}"],
            'stringable-object' => [
                ['foo' => new class() {
                    public function __toString(): string
                    {
                        return 'stringable-object';
                    }
                }],
                'foo: stringable-object',
            ],
        ];
    }

    /**
     * @dataProvider contextProvider
     *
     * @param array $context
     * @param string $expected
     */
    public function testMessageContext(array $context, string $expected): void
    {
        $context = array_merge($context, ['category' => 'app', 'time' => 1508160390.6083]);
        $this->collectOneAndExport(LogLevel::INFO, 'message', $context);
        $expected = '2017-10-16 13:26:30.608300 [info][app] message'
            . "\n\nMessage context:\n\n{$expected}\ncategory: 'app'\ntime: 1508160390.6083\n"
        ;
        $this->assertSame($expected, $this->target->formatMessages());
    }

    /**
     * @dataProvider contextProvider
     *
     * @param array $commonContext
     * @param string $expected
     */
    public function testSetCommonContext(array $commonContext, string $expected): void
    {
        $this->target->setCommonContext($commonContext);
        $this->collectOneAndExport(LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1508160390.6083]);
        $expected = '2017-10-16 13:26:30.608300 [info][app] message'
            . "\n\nMessage context:\n\ncategory: 'app'\ntime: 1508160390.6083"
            . "\n\nCommon context:\n\n{$expected}\n"
        ;
        $this->assertSame($commonContext, $this->target->getCommonContext());
        $this->assertSame($expected, $this->target->formatMessages());
    }

    public function testSetFormatWithoutMessageContextAndSetCommonContext(): void
    {
        $this->target->setCommonContext($commonContext = ['foo' => 'bar', 'baz' => true]);
        $this->target->setFormat(static function (Message $message, array $commonContext) {
            return "[{$message->level()}] {$message->message()}, common context: " . json_encode($commonContext);
        });
        $this->collectOneAndExport(LogLevel::INFO, 'message');
        $expected = '[info] message, common context: {"foo":"bar","baz":true}';

        $this->assertSame($commonContext, $this->target->getCommonContext());
        $this->assertSame($expected, $this->target->formatMessages());
    }

    public function invalidCallableReturnStringProvider(): array
    {
        return [
            'string' => [fn () => true],
            'int' => [fn () => 1],
            'float' => [fn () => 1.1],
            'array' => [fn () => []],
            'callable' => [fn () => fn () => 'a'],
            'object' => [fn () => new stdClass()],
        ];
    }

    /**
     * @dataProvider invalidCallableReturnStringProvider
     *
     * @param callable $value
     */
    public function testFormatMessageThrowExceptionForFormatCallableReturnNotBoolean(callable $value): void
    {
        $this->target->setFormat($value);
        $this->target->collect([new Message(LogLevel::INFO, 'test', ['foo' => 'bar'])], false);
        $this->expectException(RuntimeException::class);
        $this->target->formatMessages();
    }

    public function invalidMessageListProvider(): array
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
     * @dataProvider invalidMessageListProvider
     *
     * @param array $messageList
     */
    public function testCollectThrowExceptionForNonInstanceMessages(array $messageList): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->target->collect($messageList, true);
    }

    /**
     * @dataProvider invalidCallableReturnStringProvider
     *
     * @param callable $value
     */
    public function testFormatMessageThrowExceptionForPrefixCallableReturnNotBoolean(callable $value): void
    {
        $this->target->setPrefix($value);
        $this->target->collect([new Message(LogLevel::INFO, 'test', ['foo' => 'bar'])], false);
        $this->expectException(RuntimeException::class);
        $this->target->formatMessages();
    }

    private function collectOneAndExport(string $level, string $message, array $context = []): void
    {
        $this->target->collect([new Message($level, $message, $context)], true);
    }
}
