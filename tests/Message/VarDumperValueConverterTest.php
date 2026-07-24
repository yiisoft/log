<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\Message;

use PHPUnit\Framework\TestCase;
use stdClass;
use Yiisoft\Log\Message\VarDumperValueConverter;

final class VarDumperValueConverterTest extends TestCase
{
    public static function dataConvert(): array
    {
        return [
            'string' => ["'foo'", 'foo'],
            'int' => ['1', 1],
            'float' => ['1.1', 1.1],
            'null' => ['null', null],
            'array' => ['[]', []],
        ];
    }

    /**
     * @dataProvider dataConvert
     */
    public function testConvert(string $expected, mixed $value): void
    {
        $converter = new VarDumperValueConverter();

        $this->assertSame($expected, $converter($value));
    }

    public function testStringableObjectUsesToString(): void
    {
        $converter = new VarDumperValueConverter();
        $object = new class {
            public function __toString(): string
            {
                return 'stringable-object';
            }
        };

        $this->assertSame('stringable-object', $converter($object));
    }

    public function testObjectWithoutToStringUsesVarDumper(): void
    {
        $converter = new VarDumperValueConverter();

        $this->assertStringContainsString('stdClass', $converter(new stdClass()));
    }
}
