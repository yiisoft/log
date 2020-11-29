<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests;

use InvalidArgumentException;
use stdClass;
use PHPUnit\Framework\TestCase;
use Yiisoft\Log\MessageCategoryFilter;

final class MessageCategoryFilterTest extends TestCase
{
    private MessageCategoryFilter $categories;

    public function setUp(): void
    {
        $this->categories = new MessageCategoryFilter();
    }

    public function testIncludeAndGetIncluded(): void
    {
        $categories = ['category-1', 'category-2'];
        $this->categories->include($categories);

        $this->assertSame($categories, $this->categories->getIncluded());
    }

    public function testIncludeAndGetExcluded(): void
    {
        $categories = ['category-1', 'category-2'];
        $this->categories->exclude($categories);

        $this->assertSame($categories, $this->categories->getExcluded());
    }

    public function invalidCategoryMessageStructureProvider(): array
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
     * @dataProvider invalidCategoryMessageStructureProvider
     *
     * @param array $categories
     */
    public function testIncludeThrowExceptionForInvalidCategoryMessageStructure(array $categories): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->categories->include($categories);
    }

    /**
     * @dataProvider invalidCategoryMessageStructureProvider
     *
     * @param array $categories
     */
    public function testExcludeThrowExceptionForInvalidCategoryMessageStructure(array $categories): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->categories->exclude($categories);
    }

    public function testIsExcludedByDefault(): void
    {
        $this->assertFalse($this->categories->isExcluded('any-category'));
    }

    public function testIsExcludedWithInclude(): void
    {
        $this->categories->include(['category-1', 'category-2']);

        $this->assertFalse($this->categories->isExcluded('category-1'));
        $this->assertFalse($this->categories->isExcluded('category-2'));
        $this->assertTrue($this->categories->isExcluded('not-included'));
    }

    public function testIsExcludedWithExclude(): void
    {
        $this->categories->exclude(['category-1', 'category-2']);

        $this->assertTrue($this->categories->isExcluded('category-1'));
        $this->assertTrue($this->categories->isExcluded('category-2'));
        $this->assertFalse($this->categories->isExcluded('not-excluded'));
    }

    public function testIsExcludedWithIncludeAndExclude(): void
    {
        $this->categories->include(['category-1', 'category-2']);
        $this->categories->exclude(['category-2', 'category-3']);

        $this->assertFalse($this->categories->isExcluded('category-1'));
        $this->assertTrue($this->categories->isExcluded('category-2'));
        $this->assertTrue($this->categories->isExcluded('category-3'));
        $this->assertTrue($this->categories->isExcluded('not-included'));
        $this->assertTrue($this->categories->isExcluded('not-excluded'));
    }
}
