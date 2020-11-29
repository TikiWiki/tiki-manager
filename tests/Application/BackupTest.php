<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Application;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use TikiManager\Application\Backup;
use TikiManager\Style\TikiManagerStyle;

/**
 * Class BackupTest
 * @package TikiManager\Tests\Application
 * @group unit
 */
class BackupTest extends TestCase
{
    /** @var BufferedOutput */
    protected $output;

    /** @var TikiManagerStyle */
    protected $io;

    public function setUp()
    {
        $input = new ArrayInput([]);
        $this->output = $ouput = new BufferedOutput();
        $this->io = new TikiManagerStyle($input, $ouput);
    }

    /**
     * @covers \TikiManager\Application\Backup::fixPermissions
     */
    public function testFixPermissions()
    {
        $path = vfsStream::setup();

        $stub = $this->createPartialMock(Backup::class, ['getFilePerm', 'getFileUser', 'getFileGroup']);

        $stub->setIO($this->io);
        $stub->expects(self::once())->method('getFilePerm')->willReturn(0770);
        $stub->expects(self::once())->method('getFileUser')->willReturn(vfsStream::OWNER_USER_1);
        $stub->expects(self::once())->method('getFileGroup')->willReturn(vfsStream::GROUP_USER_1);

        $stub->fixPermissions($path->url());

        $this->assertEquals(0770, $path->getPermissions());
        $this->assertTrue($path->isOwnedByUser(vfsStream::OWNER_USER_1));
        $this->assertTrue($path->isOwnedByGroup(vfsStream::GROUP_USER_1));

        $outputContent = $this->output->fetch();
        $this->assertNotContains('Failed to chmod file', $outputContent);
        $this->assertNotContains('Failed to chown file', $outputContent);
        $this->assertNotContains('Failed to chgrp file', $outputContent);
    }

    /**
     * @covers \TikiManager\Application\Backup::fixPermissions
     */
    public function testFixPermissionsInvalidUserGroup()
    {
        $path = vfsStream::setup();

        $stub = $this->createPartialMock(Backup::class, ['getFilePerm', 'getFileUser', 'getFileGroup']);

        $origUser = $path->getUser();
        $origGroup = $path->getGroup();

        $stub->setIO($this->io);
        $stub->expects(self::once())->method('getFilePerm')->willReturn(0770);
        //vfsstream only accepts uid and not names (using a name simulates a failure from chown)
        $stub->expects(self::once())->method('getFileUser')->willReturn('user1');
        //vfsstream only accepts gid and not names (using a name simulates a failure from chown)
        $stub->expects(self::once())->method('getFileGroup')->willReturn('user1');

        $stub->fixPermissions($path->url());

        $this->assertEquals(0770, $path->getPermissions());
        $this->assertTrue($path->isOwnedByUser($origUser));
        $this->assertTrue($path->isOwnedByGroup($origGroup));

        $outputContent = $this->output->fetch();
        $this->assertNotContains('Failed to chmod file', $outputContent);
        $this->assertContains('Failed to chown file', $outputContent);
        $this->assertContains('Failed to chgrp file', $outputContent);
    }
}
