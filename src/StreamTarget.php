<?php

declare(strict_types=1);

namespace Yiisoft\Log;

use InvalidArgumentException;
use RuntimeException;
use Psr\Log\LogLevel;

use function error_get_last;
use function fclose;
use function flock;
use function fopen;
use function fwrite;
use function get_resource_type;
use function is_resource;
use function sprintf;
use function stream_get_meta_data;

use function is_string;

use const LOCK_EX;
use const LOCK_UN;

/**
 * StreamTarget is the log target that writes to the specified output stream.
 */
final class StreamTarget extends Target
{
    /**
     * @param resource|string $stream A string stream identifier or a stream resource.
     * @param string[] $levels The {@see LogLevel log message levels} that this target is interested in.
     * @param string[] $categories The log message categories that this target is interested in.
     * @param string[] $exceptCategories The log message categories that this target is NOT interested in.
     * @param callable|null $format A PHP callable that returns a string representation of the log message.
     * @param callable|null $prefix A PHP callable that returns a string to be prefixed to every exported message.
     * @param string|null $timestampFormat The date format for the log timestamp.
     * @param int $exportInterval How many messages should be accumulated before they are exported.
     * @param bool|callable $enabled Whether this target is enabled, or a PHP callable that returns a boolean.
     */
    public function __construct(
        private $stream = 'php://stdout',
        array $levels = [],
        array $categories = [],
        array $exceptCategories = [],
        ?callable $format = null,
        ?callable $prefix = null,
        ?string $timestampFormat = null,
        int $exportInterval = self::DEFAULT_EXPORT_INTERVAL,
        bool|callable $enabled = true,
    ) {
        parent::__construct($levels, $categories, $exceptCategories, $format, $prefix, $timestampFormat, $exportInterval, $enabled);
    }

    protected function export(): void
    {
        $stream = $this->createStream();
        flock($stream, LOCK_EX);

        if (fwrite($stream, $this->formatMessages("\n")) === false) {
            flock($stream, LOCK_UN);
            fclose($stream);
            throw new RuntimeException(sprintf(
                'Unable to export the log because of an error writing to the stream: %s',
                error_get_last()['message'] ?? '',
            ));
        }

        $this->stream = stream_get_meta_data($stream)['uri'];
        flock($stream, LOCK_UN);
        fclose($stream);
    }

    /**
     * Check and create a stream resource.
     *
     * @throws RuntimeException if the stream cannot be opened.
     * @throws InvalidArgumentException if the stream is invalid.
     *
     * @return resource The stream resource.
     */
    private function createStream()
    {
        $stream = $this->stream;

        if (is_string($stream)) {
            $stream = @fopen($stream, 'ab');
            if ($stream === false) {
                throw new RuntimeException(sprintf(
                    'The "%s" stream cannot be opened.',
                    (string) $this->stream,
                ));
            }
        }

        /** @psalm-suppress DocblockTypeContradiction */
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException(sprintf(
                'Invalid stream provided. It must be a string stream identifier or a stream resource, "%s" received.',
                get_debug_type($stream),
            ));
        }

        return $stream;
    }
}
