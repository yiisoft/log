<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\Message;

use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;
use stdClass;
use Yiisoft\Log\Message;
use Yiisoft\Log\Message\Formatter;

use function array_merge;
use function date;
use function json_encode;
use function strtoupper;

final class FormatterTest extends TestCase
{
    private Formatter $formatter;

    public function setUp(): void
    {
        $this->formatter = new Formatter();
    }

    public function contextProvider(): array
    {
        return [
            'string' => [['foo' => 'a'], "foo: 'a'"],
            'int' => [['foo' => 1], 'foo: 1'],
            'float' => [['foo' => 1.1], 'foo: 1.1'],
            'null' => [['foo' => null], 'foo: null'],
            'array' => [['foo' => []], 'foo: []'],
            'callable' => [['foo' => fn () => null], 'foo: fn () => null'],
            'exception' => [['foo' => $exception = new Exception('some error')], "foo: {$exception->__toString()}"],
            'stringable-object' => [
                ['foo' => new class () {
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
    public function testDefaultFormat(array $context, string $expected): void
    {
        $context = array_merge($context, ['category' => 'app', 'time' => 1508160390.6083]);
        $message = new Message(LogLevel::INFO, 'message', $context);
        $expected = '2017-10-16 13:26:30.608300 [info][app] message'
            . "\n\nMessage context:\n\n{$expected}\ncategory: 'app'\ntime: 1508160390.6083\n"
        ;
        $this->assertSame($expected, $this->formatter->format($message, []));
    }

    /**
     * @dataProvider contextProvider
     *
     * @param array $commonContext
     * @param string $expected
     */
    public function testDefaultFormatWithCommonContext(array $commonContext, string $expected): void
    {
        $message = new Message(LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1508160390.6083]);
        $expected = '2017-10-16 13:26:30.608300 [info][app] message'
            . "\n\nMessage context:\n\ncategory: 'app'\ntime: 1508160390.6083"
            . "\n\nCommon context:\n\n{$expected}\n"
        ;
        $this->assertSame($expected, $this->formatter->format($message, $commonContext));
    }

    public function testFormatWithSetFormat(): void
    {
        $this->formatter->setFormat(static function (Message $message, array $commonContext) {
            $context = json_encode($commonContext);
            return "[{$message->level()}][{$message->context('category')}] {$message->message()}\n{$context}\n";
        });
        $message = new Message(LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1508160390.6083]);
        $expected = "[info][app] message\n{\"foo\":\"bar\"}\n";

        $this->assertSame($expected, $this->formatter->format($message, ['foo' => 'bar']));
    }

    public function testFormatWithSetFormatNotIncludingCommonContext(): void
    {
        $this->formatter->setFormat(static function (Message $message) {
            return "[{$message->level()}][{$message->context('category')}] {$message->message()}";
        });
        $message = new Message(LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1508160390.6083]);
        $expected = '[info][app] message';

        $this->assertSame($expected, $this->formatter->format($message, ['foo' => 'bar']));
    }

    public function testFormatWithSetPrefix(): void
    {
        $this->formatter->setPrefix(static fn () => 'Prefix: ');
        $message = new Message(LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1508160390.6083]);
        $expected = '2017-10-16 13:26:30.608300 Prefix: [info][app] message'
            . "\n\nMessage context:\n\ncategory: 'app'\ntime: 1508160390.6083\n"
        ;
        $this->assertSame($expected, $this->formatter->format($message, []));
    }

    public function testFormatWithSetTimestampFormat(): void
    {
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $message = new Message(LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1508160390.6083]);
        $expected = '2017-10-16 13:26:30 [info][app] message'
            . "\n\nMessage context:\n\ncategory: 'app'\ntime: 1508160390.6083\n"
        ;
        $this->assertSame($expected, $this->formatter->format($message, []));
    }

    public function testFormatWithTimeCommaSeparated(): void
    {
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $message = new Message(LogLevel::INFO, 'message', ['category' => 'app', 'time' => '1508160390,6083']);
        $expected = '2017-10-16 13:26:30 [info][app] message'
            . "\n\nMessage context:\n\ncategory: 'app'\ntime: '1508160390,6083'\n"
        ;
        $this->assertSame($expected, $this->formatter->format($message, []));
    }

    public function testFormatWithSetFormatAndSetPrefix(): void
    {
        $this->formatter->setFormat(static fn (Message $message) => "({$message->level()}) {$message->message()}");
        $this->formatter->setPrefix(
            static function (Message $message) {
                $category = strtoupper($message->context('category'));
                $time = date('H:i:s', $message->context('time'));
                return "{$category}: ({$time})";
            }
        );

        $time = 1508160390;
        $message = new Message(LogLevel::INFO, 'message', ['category' => 'app', 'time' => $time]);

        $this->assertSame(
            'APP: (' . date('H:i:s', $time) . ')(info) message',
            $this->formatter->format($message, [])
        );
    }

    public function testFormatWithContextAndSetFormat(): void
    {
        $this->formatter->setFormat(static function (Message $message) {
            $context = json_encode($message->context());
            return "({$message->level()}) {$message->message()}, context: {$context}";
        });
        $message = new Message(LogLevel::INFO, 'message', ['foo' => 'bar', 'params' => ['baz' => true]]);
        $expected = '(info) message, context: {"foo":"bar","params":{"baz":true}}';
        $this->assertSame($expected, $this->formatter->format($message, []));
    }

    public function testFormatWithTraceInContext(): void
    {
        $timestamp = 1508160390;
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $message = new Message(
            LogLevel::INFO,
            'message',
            ['category' => 'app', 'time' => $timestamp, 'trace' => [['file' => '/path/to/file', 'line' => 99]]],
        );

        $expected = "2017-10-16 13:26:30 [info][app] message\n\nMessage context:\n\n"
            . "trace:\n    in /path/to/file:99\n"
            . "category: 'app'\ntime: {$timestamp}\n";

        $this->assertSame($expected, $this->formatter->format($message, []));
    }

    public function invalidCallableReturnStringProvider(): array
    {
        return [
            'string' => [fn () => true],
            'int' => [fn () => 1],
            'float' => [fn () => 1.1],
            'array' => [fn () => []],
            'null' => [fn () => null],
            'callable' => [fn () => static fn () => 'a'],
            'object' => [fn () => new stdClass()],
        ];
    }

    /**
     * @dataProvider invalidCallableReturnStringProvider
     *
     * @param callable $value
     */
    public function testFormatThrowExceptionForFormatCallableReturnNotString(callable $value): void
    {
        $this->formatter->setFormat($value);
        $this->expectException(RuntimeException::class);
        $this->formatter->format(new Message(LogLevel::INFO, 'test', ['foo' => 'bar']), []);
    }

    /**
     * @dataProvider invalidCallableReturnStringProvider
     *
     * @param callable $value
     */
    public function testFormatMessageThrowExceptionForPrefixCallableReturnNotString(callable $value): void
    {
        $this->formatter->setPrefix($value);
        $this->expectException(RuntimeException::class);
        $this->formatter->format(new Message(LogLevel::INFO, 'test', ['foo' => 'bar']), []);
    }
}
