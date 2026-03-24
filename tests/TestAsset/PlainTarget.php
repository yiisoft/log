<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\TestAsset;

use Yiisoft\Log\Target;

final class PlainTarget extends Target
{
    protected function export(): void {}
}
