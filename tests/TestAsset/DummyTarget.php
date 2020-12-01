<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\TestAsset;

use Yiisoft\Log\MessageFormatter;
use Yiisoft\Log\Target;

final class DummyTarget extends Target
{
    private int $exportCounter = 0;
    private array $exportMessages = [];
    private MessageFormatter $exportFormatter;

    public function __construct()
    {
        parent::__construct();
        $this->setLogGlobals([]);
        $this->exportFormatter = new MessageFormatter();
    }

    public function export(): void
    {
        $this->exportCounter++;
        $this->exportMessages = $this->getMessages();
    }

    public function getExportCount(): int
    {
        return $this->exportCounter;
    }

    public function getExportMessages(): array
    {
        return $this->exportMessages;
    }

    public function getMessages(): array
    {
        return parent::getMessages();
    }

    public function formatMessages(string $separator = ''): string
    {
        if (empty($this->exportMessages)) {
            return parent::formatMessages($separator);
        }

        $formatted = '';

        foreach ($this->exportMessages as $message) {
            $formatted .= $this->exportFormatter->format($message) . $separator;
        }

        return $formatted;
    }

    public function getFormattedMessages(): array
    {
        if (empty($this->exportMessages)) {
            return parent::getFormattedMessages();
        }

        $formatted = [];

        foreach ($this->exportMessages as $key => $message) {
            $formatted[$key] = $this->exportFormatter->format($message);
        }

        return $formatted;
    }

    public function setFormat(callable $format): self
    {
        $this->exportFormatter->setFormat($format);
        return parent::setFormat($format);
    }

    public function setPrefix(callable $prefix): self
    {
        $this->exportFormatter->setPrefix($prefix);
        return parent::setPrefix($prefix);
    }

    public function setTimestampFormat(string $format): self
    {
        $this->exportFormatter->setTimestampFormat($format);
        return parent::setTimestampFormat($format);
    }
}
