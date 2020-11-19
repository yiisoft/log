<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\TestAsset;

use Yiisoft\Log\Target;
use Yiisoft\Log\Tests\TargetTest;

class TestTarget extends Target
{
    public function __construct()
    {
        $this->setExportInterval(1);
    }

    /**
     * Exports log [[messages]] to a specific destination.
     * Child classes must implement this method.
     */
    public function export(): void
    {
        TargetTest::$messages = array_merge(TargetTest::$messages, $this->getMessages());
        $this->setMessages([]);
    }

    public function getContextMessage(): string
    {
        return parent::getContextMessage();
    }
}
