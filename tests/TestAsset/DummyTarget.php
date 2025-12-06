<?php

declare(strict_types=1);

namespace Yiisoft\Log\Tests\TestAsset;

use Yiisoft\Log\Message;
use Yiisoft\Log\Message\Formatter;
use Yiisoft\Log\Target;

final class DummyTarget extends Target
{
    private int $exportCounter = 0;
    private array $exportMessages = [];
    private Formatter $exportFormatter;

    public function __construct(array $levels = [])
    {
        parent::__construct($levels);
        $this->exportFormatter = new Formatter();
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

    /**
     * @return Message[]
     */
    public function getExportMessages(): array
    {
        return $this->exportMessages;
    }

    /**
     * @return Message[]
     */
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
            $formatted .= $this->exportFormatter->format($message, $this->getCommonContext()) . $separator;
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
            $formatted[$key] = $this->exportFormatter->format($message, $this->getCommonContext());
        }

        return $formatted;
    }

    public function getCommonContext(): array
    {
        return parent::getCommonContext();
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
