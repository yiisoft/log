<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;
use stdClass;
use Yiisoft\Log\MessageFormatter;

use function date;
use function json_encode;
use function strtoupper;

class MessageFormatterTest extends TestCase
{
    private MessageFormatter $formatter;

    public function setUp(): void
    {
        $this->formatter = new MessageFormatter();
    }

    public function testDefaultFormat(): void
    {
        $message = [LogLevel::INFO, 'message', ['category' => 'application', 'time' => 1508160390.6083]];
        $expected = '2017-10-16 13:26:30.608300 [info][application] message';

        $this->assertSame($expected, $this->formatter->format($message));
    }

    public function testFormatWithSetFormat(): void
    {
        $this->formatter->setFormat(static function (array $message) {
            [$level, $text, $context] = $message;
            return "[{$level}][{$context['category']}] {$text}";
        });
        $message = [LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1508160390.6083]];
        $expected = '[info][app] message';

        $this->assertSame($expected, $this->formatter->format($message));
    }

    public function testFormatWithSetPrefix(): void
    {
        $this->formatter->setPrefix(static fn () => 'Prefix: ');
        $message = [LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1508160390.6083]];
        $expected = '2017-10-16 13:26:30.608300 Prefix: [info][app] message';

        $this->assertSame($expected, $this->formatter->format($message));
    }

    public function testFormatWithSetTimestampFormat(): void
    {
        $this->formatter->setTimestampFormat('Y-m-d H:i:s');
        $message = [LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1508160390.6083]];
        $expected = '2017-10-16 13:26:30 [info][app] message';

        $this->assertSame($expected, $this->formatter->format($message));
    }

    public function testFormatWithSetFormatAndSetPrefix(): void
    {
        $this->formatter->setFormat(static fn (array $message) => "({$message[0]}) {$message[1]}");
        $this->formatter->setPrefix(static function (array $message) {
            $category = strtoupper($message[2]['category']);
            $time = date('H:i:s', $message[2]['time']);
            return "{$category}: ({$time})";
        });
        $message = [LogLevel::INFO, 'message', ['category' => 'app', 'time' => 1508160390]];
        $expected = 'APP: (13:26:30)(info) message';

        $this->assertSame($expected, $this->formatter->format($message));
    }

    public function testFormatWithSetFormatAndSetGlobalsAndParams(): void
    {
        $this->formatter->setFormat(static function (array $message) {
            [$level, $text, $context] = $message;
            $globalContext = json_encode(['globals' => $context['globals'], 'params' => $context['params']]);
            return "({$level}) {$text}, global context: {$globalContext}";
        });
        $message = [LogLevel::INFO, 'message', ['globals' => ['foo' => 'bar'], 'params' => ['baz' => true]]];
        $expected = '(info) message, global context: {"globals":{"foo":"bar"},"params":{"baz":true}}';

        $this->assertSame($expected, $this->formatter->format($message));
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
    public function testFormatThrowExceptionForFormatCallableReturnNotString(callable $value): void
    {
        $this->formatter->setFormat($value);
        $this->expectException(RuntimeException::class);
        $this->formatter->format([LogLevel::INFO, 'test', ['foo' => 'bar']]);
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
        $this->formatter->format([LogLevel::INFO, 'test', ['foo' => 'bar']]);
    }
}
