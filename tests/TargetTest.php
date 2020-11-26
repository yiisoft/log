<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests;

use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use PHPUnit\Framework\TestCase;
use stdClass;
use Yiisoft\Log\Logger;
use Yiisoft\Log\LogRuntimeException;
use Yiisoft\Log\Tests\TestAsset\DummyTarget;

use function array_column;
use function array_merge;
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
        $filter = array_merge($filter, ['logVars' => []]);

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

        $this->assertCount(
            count($expected),
            $messages,
            'Expected ' . implode(',', $expected) . ', got ' . implode(',', array_column($messages, 0))
        );

        $i = 0;
        foreach ($expected as $e) {
            $this->assertEquals('test' . $e, $messages[$i++][1]);
        }
    }

    public function testGetContextMessage(): void
    {
        $this->target->setLogVars([
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
        $this->collectOneAndExport(LogLevel::INFO, 'test', ['foo' => 'bar']);
        $contextMessage = $this->target->getExportContextMessage()[1];
        $this->assertStringContainsString('A_a', $contextMessage);
        $this->assertStringNotContainsString('A_b', $contextMessage);
        $this->assertStringContainsString('A_c', $contextMessage);
        $this->assertStringContainsString('B_a', $contextMessage);
        $this->assertStringNotContainsString('B_b', $contextMessage);
        $this->assertStringNotContainsString('B_c', $contextMessage);
        $this->assertStringContainsString('C_a', $contextMessage);
        $this->assertStringContainsString('C_b', $contextMessage);
        $this->assertStringContainsString('C_c', $contextMessage);
        $this->assertStringNotContainsString('D_a', $contextMessage);
        $this->assertStringNotContainsString('D_b', $contextMessage);
        $this->assertStringNotContainsString('D_c', $contextMessage);
        $this->assertStringNotContainsString('E_a', $contextMessage);
        $this->assertStringNotContainsString('E_b', $contextMessage);
        $this->assertStringNotContainsString('E_c', $contextMessage);
    }

    public function testGetEnabled(): void
    {
        $this->target->enable();
        $this->assertTrue($this->target->isEnabled());

        $this->target->disable();
        $this->assertFalse($this->target->isEnabled());

        $this->target->setEnabled(static function ($target) {
            return empty($target->messages);
        });
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
        $this->expectException(LogRuntimeException::class);
        $this->target->isEnabled();
    }

    public function testFormatTimestamp(): void
    {
        $text = 'message';
        $level = LogLevel::INFO;
        $category = 'application';
        $timestamp = 1508160390.6083;

        $this->target->setTimestampFormat('Y-m-d H:i:s');

        $expectedWithoutMicro = '2017-10-16 13:26:30 [info][application] message';
        $this->collectOneAndExport($level, $text, ['category' => $category, 'time' => $timestamp]);
        $this->assertSame($expectedWithoutMicro, $this->target->formatMessages());

        $this->target->setTimestampFormat('Y-m-d H:i:s.u');

        $expectedWithMicro = '2017-10-16 13:26:30.608300 [info][application] message';
        $this->collectOneAndExport($level, $text, ['category' => $category, 'time' => $timestamp]);
        $this->assertSame($expectedWithMicro, $this->target->formatMessages());

        $this->target->setTimestampFormat('Y-m-d H:i:s');
        $timestamp = 1508160390;

        $expectedWithoutMicro = '2017-10-16 13:26:30 [info][application] message';
        $this->collectOneAndExport($level, $text, ['category' => $category, 'time' => $timestamp]);
        $this->assertSame($expectedWithoutMicro, $this->target->formatMessages());

        $this->target->setTimestampFormat('D d F Y');

        $expectedCustom = 'Mon 16 October 2017 [info][application] message';
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

        $expectedWithMicro = '2017-10-16 13:26:30.608300 [info][application] message';
        $this->collectOneAndExport($level, $text, ['time' => $timestamp]);
        $this->assertSame($expectedWithMicro, $this->target->formatMessages());

        $this->target->setTimestampFormat('Y-m-d H:i:s');
        $expectedPatternWithoutMicro = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \[info\]\[application\] message$/';

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
            ['time' => $timestamp, 'trace' => [['file' => '/path/to/file', 'line' => 99]]],
        );

        $expected = "2017-10-16 13:26:30 [info][application] message\n    in /path/to/file:99";

        $this->assertSame($expected, $this->target->formatMessages());
    }

    public function invalidMessageStructureProvider(): array
    {
        return [
            'not-exist-index-0' => [['level' => 'info', 1 => 'message', 2 => []]],
            'not-exist-index-1' => [['level', 5 => 'message', 2 => []]],
            'not-exist-index-2' => [['level', 'message', 'context' => []]],
            'non-array-context' => [['level', 'message', 'context']],
        ];
    }

    /**
     * @dataProvider invalidMessageStructureProvider
     *
     * @param array $message
     */
    public function testCollectThrowExceptionForInvalidMessageStructure(array $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->target->collect([$message], true);
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

    /**
     * @dataProvider invalidStringListProvider
     *
     * @param array $list
     */
    public function testSetLogVarsThrowExceptionForNonStringList(array $list): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->target->setLogVars($list);
    }

    public function testSetFormat(): void
    {
        $this->target->setFormat(static function (array $message) {
            [$level, $text, $context] = $message;
            return "[{$level}][{$context['category']}] {$text}";
        });

        $expected = '[info][app] message';
        $this->collectOneAndExport(LogLevel::INFO, 'message', ['category' => 'app']);
        $this->assertSame($expected, $this->target->formatMessages());
    }

    public function testSetPrefix(): void
    {
        $this->target->setPrefix(static fn () => 'Prefix: ');
        $expected = '2017-10-16 13:26:30.608300 Prefix: [info][app] message';
        $this->collectOneAndExport(LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1508160390.6083]);
        $this->assertSame($expected, $this->target->formatMessages());
    }

    public function testSetFormatAndSetPrefix(): void
    {
        $this->target->setFormat(static fn (array $message) => "({$message[0]}) {$message[1]}");
        $this->target->setPrefix(static fn (array $message) => strtoupper($message[2]['category']) . ': ');

        $expected = 'APP: (info) message';
        $this->collectOneAndExport(LogLevel::INFO, 'message', ['category' => 'app']);
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
        $this->target->collect([[LogLevel::INFO, 'test', ['foo' => 'bar']]], false);
        $this->expectException(LogRuntimeException::class);
        $this->target->formatMessages();
    }

    /**
     * @dataProvider invalidCallableReturnStringProvider
     *
     * @param callable $value
     */
    public function testFormatMessageThrowExceptionForPrefixCallableReturnNotBoolean(callable $value): void
    {
        $this->target->setPrefix($value);
        $this->target->collect([[LogLevel::INFO, 'test', ['foo' => 'bar']]], false);
        $this->expectException(LogRuntimeException::class);
        $this->target->formatMessages();
    }

    private function collectOneAndExport(string $level, string $message, array $context = []): void
    {
        $this->target->collect([[$level, $message, $context]], true);
    }
}
