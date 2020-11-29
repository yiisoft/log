<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\TestAsset;

use Yiisoft\Log\MessageFormatter;
use Yiisoft\Log\Target;

use function array_pop;

final class DummyTarget extends Target
{
    private int $exportCounter = 0;
    private array $exportMessages = [];
    private array $exportContextMessage = [];
    private MessageFormatter $exportFormatter;

    public function __construct()
    {
        $this->exportFormatter = new MessageFormatter();
        parent::__construct();
    }

    public function export(): void
    {
        $this->exportCounter++;
        $this->exportMessages = $this->getMessages();
        $this->exportContextMessage = array_pop($this->exportMessages);
    }

    public function getExportCount(): int
    {
        return $this->exportCounter;
    }

    public function getExportMessages(): array
    {
        return $this->exportMessages;
    }

    public function getExportContextMessage(): array
    {
        return $this->exportContextMessage;
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

    public function getMessages(): array
    {
        return parent::getMessages();
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
