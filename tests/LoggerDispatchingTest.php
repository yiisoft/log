<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Log {

    use Yiisoft\Log\Tests\LoggerDispatchingTest;

    function microtime($get_as_float)
    {
        if (LoggerDispatchingTest::$microtimeIsMocked) {
            return LoggerDispatchingTest::microtime(func_get_args());
        }

        return \microtime($get_as_float);
    }
}

namespace Yiisoft\Log\Tests {

    use Psr\Log\LogLevel;
    use yii\exceptions\UserException;
    use Yiisoft\Log\Logger;
    use Yiisoft\Log\Target;
    use yii\tests\TestCase;

    /**
     * @group log
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

        protected function setUp()
        {
            static::$microtimeIsMocked = false;
            $this->logger = new Logger([]);
        }

        /**
         * @covers \Yiisoft\Log\Logger::dispatch()
         */
        public function testDispatchWithDisabledTarget()
        {
            $target = $this->getMockBuilder(Target::class)
                ->setMethods(['collect'])
                ->getMockForAbstractClass();

            $target->expects($this->never())->method($this->anything());
            $target->enabled = false;

            $logger = new Logger(['fakeTarget' => $target]);
            $logger->messages = 'messages';
            $logger->flush(true);
        }

        /**
         * @covers \Yiisoft\Log\Logger::dispatch()
         */
        public function testDispatchWithSuccessTargetCollect()
        {
            $target = $this->getMockBuilder(Target::class)
                ->setMethods(['collect'])
                ->getMockForAbstractClass();

            $target->expects($this->once())
                ->method('collect')
                ->with(
                    $this->equalTo('messages'),
                    $this->equalTo(true)
                );

            $logger = new Logger(['fakeTarget' => $target]);

            $logger->messages = 'messages';
            $logger->flush(true);
        }

        /**
         * @covers \Yiisoft\Log\Logger::dispatch()
         */
        public function testDispatchWithFakeTarget2ThrowExceptionWhenCollect()
        {
            static::$microtimeIsMocked = true;

            $target1 = $this->getMockBuilder(Target::class)
                ->setMethods(['collect'])
                ->getMockForAbstractClass();

            $target2 = $this->getMockBuilder(Target::class)
                ->setMethods(['collect'])
                ->getMockForAbstractClass();

            $target1->expects($this->exactly(2))
                ->method('collect')
                ->withConsecutive(
                    [$this->equalTo('messages'), $this->equalTo(true)],
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
                    $this->equalTo('messages'),
                    $this->equalTo(true)
                )->will($this->throwException(new UserException('some error')));

            $logger = new Logger([
                'fakeTarget1' => $target1,
                'fakeTarget2' => $target2,
            ]);

            static::$functions['microtime'] = function ($arguments) {
                $this->assertEquals([true], $arguments);
                return 'time data';
            };

            $logger->messages = 'messages';
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
