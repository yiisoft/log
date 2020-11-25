<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\TestAsset;

use Yiisoft\Log\Target;

final class DummyTarget extends Target
{
    private int $exportCounter = 0;

    public function export(): void
    {
        $this->exportCounter++;
    }

    public function getExportCount(): int
    {
        return $this->exportCounter;
    }

    public function formatMessage(array $message): string
    {
        return parent::formatMessage($message);
    }

    public function getMessagePrefix(array $message): string
    {
        return parent::getMessagePrefix($message);
    }

    public function getContextMessage(): string
    {
        return parent::getContextMessage();
    }
}
