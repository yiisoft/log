<?php
namespace Yiisoft\Log {

    use Yiisoft\Log\Tests\LoggerDispatchingTest;

    function microtime($getAsFloat)
    {
        if (LoggerDispatchingTest::$microtimeIsMocked) {
            return LoggerDispatchingTest::microtime(func_get_args());
        }

        return \microtime($getAsFloat);
    }
}

namespace Yiisoft\Log\Tests {

    use Psr\Log\LogLevel;
    use Yiisoft\Log\Logger;
    use Yiisoft\Log\Target;

    /**
     * @group log
     * @method static int|float microtime($getAsFloat)
     */
    class LoggerDispatchingTest extends TestCase
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
            $this->setInaccessibleProperty($logger, 'messages', [
                [LogLevel::INFO, 'test', []]
            ]);
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

            $this->setInaccessibleProperty($logger, 'messages', [
                [LogLevel::INFO, 'test', []]
            ]);
            $logger->flush(true);
        }

        /**
         * @covers \Yiisoft\Log\Logger::dispatch()
         */
        public function testDispatchWithFakeTarget2ThrowExceptionWhenCollect(): void
        {
            static::$microtimeIsMocked = true;

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
                            'Unable to send log via ' . get_class($target1) . ': Exception: some error',
                            LogLevel::WARNING,
                            'Yiisoft\Log\Logger::dispatch',
                            'time data',
                            [],
                        ]],
                        true,
                    ]
                );

            $target2->expects($this->once())
                ->method('collect')
                ->with(
                    $this->equalTo([]),
                    $this->equalTo(true)
                )->will($this->throwException(new \Exception('some error')));

            $logger = new Logger([
                'fakeTarget1' => $target1,
                'fakeTarget2' => $target2,
            ]);

            static::$functions['microtime'] = function ($arguments) {
                $this->assertEquals([true], $arguments);
                return 'time data';
            };

            $this->setInaccessibleProperty($logger, 'messages', []);
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
