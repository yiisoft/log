<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\Message;

use DateTime;
use Exception;
use LogicException;
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

use const JSON_THROW_ON_ERROR;

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
            'callable' => [['foo' => fn() => null], 'foo: fn() => null'],
            'exception' => [['foo' => $exception = new Exception('some error')], "foo: {$exception->__toString()}"],
            'stringable-object' => [
                ['foo' => new class {
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
     */
    public function testDefaultFormat(array $context, string $expected): void
    {
        $context = array_merge($context, ['category' => 'app', 'time' => 1_508_160_390.6083]);
        $message = new Message(LogLevel::INFO, 'message', $context);
        $expected = '2017-10-16 13:26:30.608300 [info][app] message'
            . "\n\nMessage context:\n\n{$expected}\ncategory: 'app'\ntime: 1508160390.6083\n"
        ;
        $this->assertSame($expected, $this->formatter->format($message, []));
    }

    /**
     * @dataProvider contextProvider
     */
    public function testDefaultFormatWithCommonContext(array $commonContext, string $expected): void
    {
        $message = new Message(LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1_508_160_390.6083]);
        $expected = '2017-10-16 13:26:30.608300 [info][app] message'
            . "\n\nMessage context:\n\ncategory: 'app'\ntime: 1508160390.6083"
            . "\n\nCommon context:\n\n{$expected}\n"
        ;
        $this->assertSame($expected, $this->formatter->format($message, $commonContext));
    }

    public function testFormatWithSetFormat(): void
    {
        $this->formatter->setFormat(static function (Message $message, array $commonContext) {
            $context = json_encode($commonContext, JSON_THROW_ON_ERROR);
            return "[{$message->level()}][{$message->context('category')}] {$message->message()}\n{$context}\n";
        });
        $message = new Message(LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1_508_160_390.6083]);
        $expected = "[info][app] message\n{\"foo\":\"bar\"}\n";

        $this->assertSame($expected, $this->formatter->format($message, ['foo' => 'bar']));
    }

    public function testFormatWithSetFormatNotIncludingCommonContext(): void
    {
        $this->formatter->setFormat(static fn(Message $message) => "[{$message->level()}][{$message->context('category')}] {$message->message()}");
        $message = new Message(LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1_508_160_390.6083]);
        $expected = '[info][app] message';

        $this->assertSame($expected, $this->formatter->format($message, ['foo' => 'bar']));
    }

    public function testFormatWithSetPrefix(): void
    {
        $this->formatter->setPrefix(static fn() => 'Prefix: ');
        $message = new Message(LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1_508_160_390.6083]);
        $expected = '2017-10-16 13:26:30.608300 Prefix: [info][app] message'
            . "\n\nMessage context:\n\ncategory: 'app'\ntime: 1508160390.6083\n"
        ;
        $this->assertSame($expected, $this->formatter->format($message, []));
    }

    public function testFormatWithSetTimestampFormat(): void
    {
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $message = new Message(LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1_508_160_390.6083]);
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
        $this->formatter->setFormat(static fn(Message $message) => "({$message->level()}) {$message->message()}");
        $this->formatter->setPrefix(
            static function (Message $message) {
                $category = strtoupper($message->context('category'));
                $time = date('H:i:s', $message->context('time'));
                return "{$category}: ({$time})";
            },
        );

        $time = 1_508_160_390;
        $message = new Message(LogLevel::INFO, 'message', ['category' => 'app', 'time' => $time]);

        $this->assertSame(
            'APP: (' . date('H:i:s', $time) . ')(info) message',
            $this->formatter->format($message, []),
        );
    }

    public function testFormatWithContextAndSetFormat(): void
    {
        $this->formatter->setFormat(static function (Message $message) {
            $context = json_encode($message->context(), JSON_THROW_ON_ERROR);
            return "({$message->level()}) {$message->message()}, context: {$context}";
        });
        $message = new Message(LogLevel::INFO, 'message', ['foo' => 'bar', 'params' => ['baz' => true]]);
        $expected = '(info) message, context: {"foo":"bar","params":{"baz":true}}';
        $this->assertSame($expected, $this->formatter->format($message, []));
    }

    public static function dataFormatWithTraceInContext(): array
    {
        return [
            'file-and-line' => [
                'in /path/to/file:99',
                [
                    'file' => '/path/to/file',
                    'line' => 99,
                ],
            ],
            'function-and-class' => [
                'App\HomePageAction:App\{closure}',
                [
                    'function' => 'App\{closure}',
                    'class' => 'App\HomePageAction',
                    'object' => new stdClass(),
                    'type' => '->',
                    'args' => [],
                ],
            ],
            'function' => [
                'App\{closure}',
                [
                    'function' => 'App\{closure}',
                    'type' => '->',
                    'args' => [],
                ],
            ],
            'unsupported' => [
                '???',
                [
                    'something' => 'strange',
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataFormatWithTraceInContext
     */
    public function testFormatWithTraceInContext(string $expectedTrace, array $traceItem): void
    {
        $timestamp = 1_508_160_390;
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $message = new Message(
            LogLevel::INFO,
            'message',
            ['category' => 'app', 'time' => $timestamp, 'trace' => [$traceItem]],
        );

        $expected = "2017-10-16 13:26:30 [info][app] message\n\nMessage context:\n\n"
            . "trace:\n    $expectedTrace\n"
            . "category: 'app'\ntime: $timestamp\n";

        $this->assertSame($expected, $this->formatter->format($message, []));
    }

    public function testDefaultFormatWithSetConvertToString(): void
    {
        $this->formatter->setConvertToString(
            static fn(mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR),
        );
        $message = new Message(LogLevel::INFO, 'message', [
            'category' => 'app',
            'time' => 1_508_160_390,
        ]);
        $expected = '2017-10-16 13:26:30.000000 [info][app] message'
            . "\n\nMessage context:\n\ncategory: \"app\"\ntime: 1508160390"
            . "\n\nCommon context:\n\nserver: \"web\"\n"
        ;
        $this->assertSame($expected, $this->formatter->format($message, ['server' => 'web']));
    }

    public function testDefaultFormatWithSetContextFormat(): void
    {
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $this->formatter->setContextFormat(
            static function (string $trace, string $messageContext, string $commonContext): string {
                $result = '';
                if ($commonContext !== '') {
                    $result .= "\n\nCommon:\n" . $commonContext;
                }
                if ($trace !== '') {
                    $result .= "\n\nTrace:\n" . $trace;
                }
                if ($messageContext !== '') {
                    $result .= "\n\nMessage:\n" . $messageContext;
                }
                return $result;
            },
        );
        $message = new Message(LogLevel::INFO, 'message', [
            'category' => 'app',
            'time' => 1_508_160_390,
            'trace' => [['file' => '/path/to/file', 'line' => 99]],
        ]);
        $expected = '2017-10-16 13:26:30 [info][app] message'
            . "\n\nCommon:\nserver: 'web'"
            . "\n\nTrace:\ntrace:\n    in /path/to/file:99"
            . "\n\nMessage:\ncategory: 'app'\ntime: 1508160390"
        ;
        $this->assertSame($expected, $this->formatter->format($message, ['server' => 'web']));
    }

    public function testDefaultFormatWithSetContextTemplate(): void
    {
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $this->formatter->setContextTemplate("{common}{message}{trace}\n");
        $message = new Message(LogLevel::INFO, 'message', [
            'category' => 'app',
            'time' => 1_508_160_390,
            'trace' => [['file' => '/path/to/file', 'line' => 99]],
        ]);
        $expected = '2017-10-16 13:26:30 [info][app] message'
            . "\n\nCommon context:\n\nserver: 'web'"
            . "\n\nMessage context:\n\ncategory: 'app'\ntime: 1508160390"
            . "\n\nTrace:\n\ntrace:\n    in /path/to/file:99"
            . "\n"
        ;
        $this->assertSame($expected, $this->formatter->format($message, ['server' => 'web']));
    }

    public function testDefaultFormatWithSetContextTemplateEmptySections(): void
    {
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $this->formatter->setContextTemplate("{trace}{message}{common}\n");
        $message = new Message(LogLevel::INFO, 'message', [
            'category' => 'app',
            'time' => 1_508_160_390,
        ]);
        $expected = '2017-10-16 13:26:30 [info][app] message'
            . "\n\nMessage context:\n\ncategory: 'app'\ntime: 1508160390"
            . "\n"
        ;
        $this->assertSame($expected, $this->formatter->format($message, []));
    }

    public function testDefaultFormatWithSetContextTemplateOnlyCommon(): void
    {
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $this->formatter->setContextTemplate("{common}\n");
        $message = new Message(LogLevel::INFO, 'message', [
            'category' => 'app',
            'time' => 1_508_160_390,
        ]);
        $expected = '2017-10-16 13:26:30 [info][app] message'
            . "\n\nCommon context:\n\nserver: 'web'"
            . "\n"
        ;
        $this->assertSame($expected, $this->formatter->format($message, ['server' => 'web']));
    }

    public function testContextFormatTakesPrecedenceOverContextTemplate(): void
    {
        $this->formatter->setContextTemplate("{common}{message}\n");
        $this->formatter->setContextFormat(
            static fn(string $trace, string $messageContext, string $commonContext): string => '[custom]',
        );
        $message = new Message(LogLevel::INFO, 'message', [
            'category' => 'app',
            'time' => 1_508_160_390,
        ]);
        $result = $this->formatter->format($message, []);
        $this->assertStringContainsString('[custom]', $result);
        $this->assertStringNotContainsString('Common context', $result);
    }

    public function testDefaultFormatWithSetConvertToStringOverridesStringableObject(): void
    {
        $this->formatter->setConvertToString(
            static fn(mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR),
        );
        $object = new class {
            public function __toString(): string
            {
                return 'stringable-object';
            }
        };
        $message = new Message(LogLevel::INFO, 'message', [
            'category' => 'app',
            'time' => 1_508_160_390,
            'obj' => $object,
        ]);
        $result = $this->formatter->format($message, []);
        $this->assertStringContainsString('obj: {}', $result);
        $this->assertStringNotContainsString('stringable-object', $result);
    }

    public function testDefaultFormatWithSetConvertToStringDoesNotAffectTrace(): void
    {
        $called = false;
        $this->formatter->setConvertToString(static function (mixed $value) use (&$called): string {
            $called = true;
            return json_encode($value, JSON_THROW_ON_ERROR);
        });
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $message = new Message(LogLevel::INFO, 'message', [
            'time' => 1_508_160_390,
            'trace' => [['file' => '/path/to/file', 'line' => 99]],
        ]);
        $result = $this->formatter->format($message, []);
        $this->assertStringContainsString("trace:\n    in /path/to/file:99", $result);
        $this->assertTrue($called);
    }

    public function testDefaultFormatWithSetContextFormatReceivesEmptyTrace(): void
    {
        $receivedTrace = 'not-called';
        $this->formatter->setContextFormat(
            static function (string $trace, string $messageContext, string $commonContext) use (&$receivedTrace): string {
                $receivedTrace = $trace;
                return "\n" . $messageContext;
            },
        );
        $message = new Message(LogLevel::INFO, 'message', [
            'category' => 'app',
            'time' => 1_508_160_390,
        ]);
        $this->formatter->format($message, []);
        $this->assertSame('', $receivedTrace);
    }

    public function testDefaultFormatWithSetContextFormatReceivesEmptyCommonContext(): void
    {
        $receivedCommon = 'not-called';
        $this->formatter->setContextFormat(
            static function (string $trace, string $messageContext, string $commonContext) use (&$receivedCommon): string {
                $receivedCommon = $commonContext;
                return "\n" . $messageContext;
            },
        );
        $message = new Message(LogLevel::INFO, 'message', [
            'category' => 'app',
            'time' => 1_508_160_390,
        ]);
        $this->formatter->format($message, []);
        $this->assertSame('', $receivedCommon);
    }

    public function testDefaultFormatWithSetConvertToStringAndSetContextFormat(): void
    {
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $this->formatter->setConvertToString(
            static fn(mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR),
        );
        $this->formatter->setContextFormat(
            static function (string $trace, string $messageContext, string $commonContext): string {
                $result = '';
                if ($commonContext !== '') {
                    $result .= "\n[C] " . $commonContext;
                }
                if ($messageContext !== '') {
                    $result .= "\n[M] " . $messageContext;
                }
                return $result;
            },
        );
        $message = new Message(LogLevel::INFO, 'message', [
            'category' => 'app',
            'time' => 1_508_160_390,
        ]);
        $expected = '2017-10-16 13:26:30 [info][app] message'
            . "\n[C] server: \"web\""
            . "\n[M] category: \"app\"\ntime: 1508160390"
        ;
        $this->assertSame($expected, $this->formatter->format($message, ['server' => 'web']));
    }

    public function testTraceWithFileWithoutLineUsesFunction(): void
    {
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $message = new Message(
            LogLevel::INFO,
            'message',
            [
                'category' => 'app',
                'time' => 1_508_160_390,
                'trace' => [['file' => '/path/to/file', 'function' => 'myFunc']],
            ],
        );

        $result = $this->formatter->format($message, []);

        $this->assertStringNotContainsString('in /path/to/file:', $result);
        $this->assertStringContainsString('myFunc', $result);
    }

    public function testTraceWithLineWithoutFileUsesFunction(): void
    {
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $message = new Message(
            LogLevel::INFO,
            'message',
            [
                'category' => 'app',
                'time' => 1_508_160_390,
                'trace' => [['line' => 99, 'function' => 'myFunc']],
            ],
        );

        $result = $this->formatter->format($message, []);

        $this->assertStringNotContainsString(':99', $result);
        $this->assertStringContainsString('myFunc', $result);
    }

    public function invalidCallableReturnStringProvider(): array
    {
        return [
            'string' => [fn() => true],
            'int' => [fn() => 1],
            'float' => [fn() => 1.1],
            'array' => [fn() => []],
            'null' => [fn() => null],
            'callable' => [fn() => static fn() => 'a'],
            'object' => [fn() => new stdClass()],
        ];
    }

    /**
     * @dataProvider invalidCallableReturnStringProvider
     */
    public function testFormatThrowExceptionForFormatCallableReturnNotString(callable $value): void
    {
        $this->formatter->setFormat($value);
        $this->expectException(RuntimeException::class);
        $this->formatter->format(new Message(LogLevel::INFO, 'test', ['foo' => 'bar']), []);
    }

    /**
     * @dataProvider invalidCallableReturnStringProvider
     */
    public function testFormatMessageThrowExceptionForPrefixCallableReturnNotString(callable $value): void
    {
        $this->formatter->setPrefix($value);
        $this->expectException(RuntimeException::class);
        $this->formatter->format(new Message(LogLevel::INFO, 'test', ['foo' => 'bar']), []);
    }

    public function testFormatThrowExceptionForConvertToStringCallableReturnNotString(): void
    {
        $this->formatter->setConvertToString(static fn(mixed $value) => 123);
        $this->expectException(RuntimeException::class);
        $this->formatter->format(new Message(LogLevel::INFO, 'test', ['foo' => 'bar']), []);
    }

    public function testFormatThrowExceptionForContextFormatCallableReturnNotString(): void
    {
        $this->formatter->setContextFormat(
            static fn(string $trace, string $messageContext, string $commonContext) => 123,
        );
        $this->expectException(RuntimeException::class);
        $this->formatter->format(new Message(LogLevel::INFO, 'test', ['foo' => 'bar']), []);
    }

    public static function dataTime(): array
    {
        return [
            'int' => ['1970-01-01 00:00:01.000000', 1],
            'float' => ['1970-01-01 00:00:01.230000', 1.23],
            'string-int' => ['1970-01-01 00:00:23.000000', '23'],
            'string-float' => ['1970-01-01 00:00:23.600000', '23.6'],
            'string-float-comma' => ['1970-01-01 00:00:23.600000', '23,6'],
            'datetime' => ['1970-01-01 00:00:23.00000', new DateTime('@23')],
        ];
    }

    /**
     * @dataProvider dataTime
     */
    public function testTime(string $expected, mixed $value): void
    {
        $formatter = new Formatter();
        $result = $formatter->format(new Message(LogLevel::INFO, 'test', ['time' => $value]), []);
        $this->assertStringStartsWith($expected, $result);
    }

    public function testTimeWithInvalidFloat(): void
    {
        $formatter = new Formatter();
        $message = new Message(LogLevel::INFO, 'test', ['time' => 1234231.9135123512]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid time value in log context: 1234231.9135124.');
        $formatter->format($message, []);
    }

    public function testTimeWithInvalidString(): void
    {
        $formatter = new Formatter();
        $message = new Message(LogLevel::INFO, 'test', ['time' => 'hello']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid time value in log context: "hello".');
        $formatter->format($message, []);
    }

    public function testTimeWithInvalidType(): void
    {
        $formatter = new Formatter();
        $message = new Message(LogLevel::INFO, 'test', ['time' => []]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid time value in log context. Got "array".');
        $formatter->format($message, []);
    }
}
