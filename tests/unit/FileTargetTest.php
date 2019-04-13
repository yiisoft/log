<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yii\Log\Tests\Unit;

use Psr\Log\LogLevel;
use yii\helpers\Yii;
use yii\helpers\FileHelper;
use Yii\Log\FileTarget;
use Yii\Log\Logger;
use yii\tests\TestCase;

/**
 * @group log
 */
class FileTargetTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->mockApplication();
    }

    public function booleanDataProvider()
    {
        return [
            [true],
            [false],
        ];
    }

    /**
     * Tests that log directory isn't created during init process
     * @see https://github.com/yiisoft/yii2/issues/15662
     */
    public function testInit()
    {
        $logFile = Yii::getAlias('@yii/tests/runtime/log/filetargettest.log');
        FileHelper::removeDirectory(dirname($logFile));
        new FileTarget(Yii::getAlias('@yii/tests/runtime/log/filetargettest.log'));
        $this->assertFileNotExists(
            dirname($logFile),
            'Log directory should not be created during init process'
        );
    }

    /**
     * @dataProvider booleanDataProvider
     * @param bool $rotateByCopy
     */
    public function testRotate($rotateByCopy)
    {
        $logFile = Yii::getAlias('@yii/tests/runtime/log/filetargettest.log');
        FileHelper::removeDirectory(dirname($logFile));
        mkdir(dirname($logFile), 0777, true);


        $fileTarget = (new FileTarget($logFile))
        ->setMaxFileSize(1024)
        ->setMaxLogFiles(1);

        $logger = new Logger([
            'file' => $fileTarget,
        ]);

        // one file

        $logger->log(LogLevel::WARNING, str_repeat('x', 1024));
        $logger->flush(true);

        clearstatcache();

        $this->assertFileExists($logFile);
        $this->assertFileNotExists($logFile . '.1');
        $this->assertFileNotExists($logFile . '.2');
        $this->assertFileNotExists($logFile . '.3');
        $this->assertFileNotExists($logFile . '.4');

        // exceed max size
        for ($i = 0; $i < 1024; $i++) {
            $logger->log(LogLevel::WARNING, str_repeat('x', 1024));
        }
        $logger->flush(true);

        // first rotate

        $logger->log(LogLevel::WARNING, str_repeat('x', 1024));
        $logger->flush(true);

        clearstatcache();

        $this->assertFileExists($logFile);
        $this->assertFileExists($logFile . '.1');
        $this->assertFileNotExists($logFile . '.2');
        $this->assertFileNotExists($logFile . '.3');
        $this->assertFileNotExists($logFile . '.4');

        // second rotate

        for ($i = 0; $i < 1024; $i++) {
            $logger->log(LogLevel::WARNING, str_repeat('x', 1024));
        }
        $logger->flush(true);

        clearstatcache();

        $this->assertFileExists($logFile);
        $this->assertFileExists($logFile . '.1');
        $this->assertFileNotExists($logFile . '.2');
        $this->assertFileNotExists($logFile . '.3');
        $this->assertFileNotExists($logFile . '.4');
    }
}
