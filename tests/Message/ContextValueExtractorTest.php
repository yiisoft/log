<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\Message;

use PHPUnit\Framework\TestCase;
use Yiisoft\Log\Message\ContextValueExtractor;

final class ContextValueExtractorTest extends TestCase
{
    public function testExtractWithEmptyKeyFound(): void
    {
        [$exists, $value] = ContextValueExtractor::extract(['' => 'found'], '');
        $this->assertTrue($exists);
        $this->assertSame('found', $value);
    }

    public function testExtractWithEmptyKeyNotFound(): void
    {
        [$exists, $value] = ContextValueExtractor::extract(['foo' => 'bar'], '');
        $this->assertFalse($exists);
        $this->assertNull($value);
    }

    public function testExtractWithSimpleKey(): void
    {
        $this->assertSame([true, 'bar'], ContextValueExtractor::extract(['foo' => 'bar'], 'foo'));
    }

    public function testExtractWithMissingKey(): void
    {
        $this->assertSame([false, null], ContextValueExtractor::extract(['foo' => 'bar'], 'baz'));
    }

    public function testExtractWithNestedKey(): void
    {
        $context = ['user' => ['name' => 'John']];
        $this->assertSame([true, 'John'], ContextValueExtractor::extract($context, 'user.name'));
    }

    public function testExtractWithNestedKeyNotFound(): void
    {
        $context = ['user' => ['name' => 'John']];
        $this->assertSame([false, null], ContextValueExtractor::extract($context, 'user.age'));
    }

    public function testExtractWithNestedKeyNonArrayIntermediate(): void
    {
        $context = ['user' => 'string'];
        $this->assertSame([false, null], ContextValueExtractor::extract($context, 'user.name'));
    }

    public function testExtractWithEscapedDot(): void
    {
        $context = ['user.name' => 'John'];
        $this->assertSame([true, 'John'], ContextValueExtractor::extract($context, 'user\.name'));
    }

    public function testExtractWithEscapedBackslash(): void
    {
        $context = ['user\\' => 'John'];
        $this->assertSame([true, 'John'], ContextValueExtractor::extract($context, 'user\\\\'));
    }

    public function testExtractWithDeeplyNestedKey(): void
    {
        $context = ['a' => ['b' => ['c' => 'deep']]];
        $this->assertSame([true, 'deep'], ContextValueExtractor::extract($context, 'a.b.c'));
    }

    public function testExtractWithBackslashKeyAndNestedAccess(): void
    {
        $context = ['a\\' => ['b' => 'value']];
        $this->assertSame([true, 'value'], ContextValueExtractor::extract($context, 'a\\\\.b'));
    }

    public function testExtractWithMultipleBackslashesBeforeDot(): void
    {
        $context = ['a\\\\' => ['b' => 'value']];
        $this->assertSame([true, 'value'], ContextValueExtractor::extract($context, 'a\\\\\\\\.b'));
    }

    public function testExtractWithEscapedDotAndNesting(): void
    {
        $context = ['a.b' => ['c' => 'value']];
        $this->assertSame([true, 'value'], ContextValueExtractor::extract($context, 'a\\.b.c'));
    }
}
