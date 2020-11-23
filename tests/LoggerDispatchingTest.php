<?php

declare(strict_types=1);

namespace Yiisoft\Log {

    use Yiisoft\Log\Tests\LoggerDispatchingLoggerTest;

    function microtime($getAsFloat)
    {
        if (LoggerDispatchingLoggerTest::$microtimeIsMocked) {
            return LoggerDispatchingLoggerTest::microtime(func_get_args());
        }

        return \microtime($getAsFloat);
    }
}

namespace Yiisoft\Log\Tests {

    use Exception;
    use Psr\Log\LogLevel;
    use Yiisoft\Log\Logger;
    use Yiisoft\Log\Target;

    /**
     * @group log
     * @method static int|float microtime($getAsFloat)
     */
    final class LoggerDispatchingLoggerTest extends LoggerTestCase
    {
        /**
         * @var Logger
         */
        protected $logger;

        /**
         * @var bool
         */
        public static $microtimeIsMocked = false;

        /**
         * Array of static functions
         *
         * @var array
         */
        public static $functions = [];

        protected function setUp(): void
        {
            static::$microtimeIsMocked = false;
            $this->logger = new Logger();
        }

        /**
         * @covers \Yiisoft\Log\Logger::dispatch()
         */
        public function testDispatchWithDisabledTarget(): void
        {
            /** @var Target $target */
            $target = $this->getMockBuilder(Target::class)
                ->onlyMethods(['collect'])
                ->getMockForAbstractClass();

            $target->expects($this->never())->method($this->anything());
            $target->setEnabled(false);

            $logger = new Logger(['fakeTarget' => $target]);
            $this->setInaccessibleMessages($logger, [[LogLevel::INFO, 'test', []]]);
            $logger->flush(true);
        }

        /**
         * @covers \Yiisoft\Log\Logger::dispatch()
         */
        public function testDispatchWithSuccessTargetCollect(): void
        {
            $target = $this->getMockBuilder(Target::class)
                ->onlyMethods(['collect'])
                ->getMockForAbstractClass();

            $target->expects($this->once())
                ->method('collect')
                ->with(
                    $this->equalTo([
                        [LogLevel::INFO, 'test', []]
                    ]),
                    $this->equalTo(true)
                );

            $logger = new Logger(['fakeTarget' => $target]);

            $this->setInaccessibleMessages($logger, [[LogLevel::INFO, 'test', []]]);
            $logger->flush(true);
        }

        /**
         * @covers \Yiisoft\Log\Logger::dispatch()
         */
        public function testDispatchWithFakeTarget2ThrowExceptionWhenCollect(): void
        {
            static::$microtimeIsMocked = true;
            $exception = new Exception('some error');

            $target1 = $this->getMockBuilder(Target::class)
                ->onlyMethods(['collect'])
                ->getMockForAbstractClass();

            $target2 = $this->getMockBuilder(Target::class)
                ->onlyMethods(['collect'])
                ->getMockForAbstractClass();

            $target1->expects($this->exactly(2))
                ->method('collect')
                ->withConsecutive(
                    [$this->equalTo([]), $this->equalTo(true)],
                    [
                        [[
                            LogLevel::WARNING,
                            'Unable to send log via ' . get_class($target1) . ': Exception: some error',
                            ['time' => 'time data', 'trace' => $exception->getTrace()],
                        ]],
                        true,
                    ]
                );

            $target2->expects($this->once())
                ->method('collect')
                ->with(
                    $this->equalTo([]),
                    $this->equalTo(true)
                )->will($this->throwException($exception));

            $logger = new Logger([
                'fakeTarget1' => $target1,
                'fakeTarget2' => $target2,
            ]);

            static::$functions['microtime'] = function ($arguments) {
                $this->assertEquals([true], $arguments);
                return 'time data';
            };

            $this->setInaccessibleMessages($logger, []);
            $logger->flush(true);
        }

        /**
         * @param $name
         * @param $arguments
         * @return mixed
         */
        public static function __callStatic($name, $arguments)
        {
            if (isset(static::$functions[$name]) && is_callable(static::$functions[$name])) {
                $arguments = $arguments[0] ?? $arguments;
                return forward_static_call(static::$functions[$name], $arguments);
            }
            static::fail("Function '$name' has not implemented yet!");
        }
    }
}
