<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Application\Instance;

use PHPUnit\Framework\TestCase;
use TikiManager\Access\FTP;
use TikiManager\Access\Local;
use TikiManager\Application\Instance;
use TikiManager\Application\Instance\CrontabManager;
use TikiManager\Libs\Host\Command;

/**
 * @group unit
 */
class CrontabManagerTest extends TestCase
{

    /**
     * @covers \TikiManager\Application\Instance\CrontabManager::getConsoleCommandJob
     * @covers \TikiManager\Application\Instance\CrontabManager::getCrontabLine
     */
    public function testGetConsoleCommandJob()
    {
        $instanceMock = $this->createMock(Instance::class);
        $instanceMock->type = 'local';
        $instanceMock->webroot = '/home/test/public_html';
        $instanceMock->phpexec = '/bin/php';

        $mock = $this->getMockBuilder(CrontabManager::class)
            ->setConstructorArgs([$instanceMock])
            ->setMethods(['readCrontab'])
            ->getMock();

        $command1 = 'scheduler:run';
        $command2 = 'index:rebuild';

        $crontab = [
            '0 0 * * * /bin/sh backup.sh',
            '*/5 * * * * cd /home/test/public_html && /bin/php console.php scheduler:run > /dev/null 2>&1',
            '#10,20 *  * * * cd /home/test/public_html && /bin/php console.php index:rebuild > /dev/null 2>&1',
        ];

        $mock
            ->expects($this->exactly(3))
            ->method('readCrontab')
            ->willReturn(implode(PHP_EOL, $crontab));

        $job = $mock->getConsoleCommandJob($command1);
        $this->assertInstanceOf(Instance\CronJob::class, $job);
        $this->assertTrue($job->isEnabled());
        $this->assertEquals('*/5 * * * *', $job->getTime());
        $this->assertEquals(
            'cd /home/test/public_html && /bin/php console.php scheduler:run > /dev/null 2>&1',
            $job->getCommand()
        );

        $job = $mock->getConsoleCommandJob($command2);
        $this->assertInstanceOf(Instance\CronJob::class, $job);
        $this->assertFalse($job->isEnabled());
        $this->assertEquals('10,20 *  * * *', $job->getTime());
        $this->assertEquals(
            'cd /home/test/public_html && /bin/php console.php index:rebuild > /dev/null 2>&1',
            $job->getCommand()
        );

        // Test un-configured cronjob
        $instanceMock->webroot = '/home/test/domains/my_domain/public_html';
        $job = $mock->getConsoleCommandJob($command2);
        $this->assertNull($job);
    }

    /**
     * @covers \TikiManager\Application\Instance\CrontabManager::readCrontab
     */
    public function testReadCrontabNotSupported()
    {
        $instanceMock = $this->createMock(Instance::class);
        $instanceMock->type = 'ftp';
        $instanceMock->webroot = '/home/test/public_html';
        $instanceMock->phpexec = '/bin/php';

        $accessMock = $this->createMock(FTP::class);

        $instanceMock
            ->expects($this->once())
            ->method('getBestAccess')
            ->willReturn($accessMock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/^Operation not supported/');

        $mock = $this->getMockBuilder(CrontabManager::class)
            ->setConstructorArgs([$instanceMock])
            ->setMethodsExcept(['readCrontab'])
            ->getMock();

        $mock->readCrontab();
    }

    /**
     * @covers \TikiManager\Application\Instance\CrontabManager::readCrontab
     */
    public function testReadNoCrontabSet()
    {
        $instanceMock = $this->createMock(Instance::class);
        $instanceMock->type = 'local';
        $instanceMock->webroot = '/home/test/public_html';
        $instanceMock->phpexec = '/bin/php';

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->expects($this->once())
            ->method('getReturn')
            ->willReturn(1);
        $commandMock
            ->expects($this->once())
            ->method('getStderrContent')
            ->willReturn("no crontab for root\n");
        $commandMock
            ->expects($this->once())
            ->method('run')
            ->willReturn($commandMock);

        $accessMock = $this->createMock(Local::class);
        $accessMock
            ->expects($this->once())
            ->method('createCommand')
            ->with('crontab', ['-l'])
            ->willReturn($commandMock);

        $instanceMock
            ->method('getBestAccess')
            ->willReturn($accessMock);

        $mock = $this->getMockBuilder(CrontabManager::class)
            ->setConstructorArgs([$instanceMock])
            ->setMethodsExcept(['readCrontab'])
            ->getMock();

        $crontab = $mock->readCrontab();
        $this->assertEmpty($crontab);
    }

    /**
     * @covers \TikiManager\Application\Instance\CrontabManager::readCrontab
     */
    public function testReadCrontabMissing()
    {
        $instanceMock = $this->createMock(Instance::class);
        $instanceMock->type = 'local';
        $instanceMock->webroot = '/home/test/public_html';
        $instanceMock->phpexec = '/bin/php';

        $commandMock = $this->createMock(Command::class);
        $commandMock
            ->expects($this->once())
            ->method('getReturn')
            ->willReturn(1);
        $commandMock
            ->expects($this->once())
            ->method('getStderrContent')
            ->willReturn("bash: crontab: command not found\n");
        $commandMock
            ->expects($this->once())
            ->method('run')
            ->willReturn($commandMock);

        $accessMock = $this->createMock(Local::class);
        $accessMock
            ->expects($this->once())
            ->method('createCommand')
            ->with('crontab', ['-l'])
            ->willReturn($commandMock);

        $instanceMock
            ->method('getBestAccess')
            ->willReturn($accessMock);

        $mock = $this->getMockBuilder(CrontabManager::class)
            ->setConstructorArgs([$instanceMock])
            ->setMethodsExcept(['readCrontab'])
            ->getMock();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/^Error when trying to read crontab/');

        $mock->readCrontab();
    }
}
