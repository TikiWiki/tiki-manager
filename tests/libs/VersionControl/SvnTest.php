<?php

namespace TikiManager\Tests\Libs\VersionControl;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use TikiManager\Access\Local;
use TikiManager\Application\Instance;
use TikiManager\Libs\Host\Command;
use TikiManager\Libs\VersionControl\Git;
use TikiManager\Libs\VersionControl\Svn;

class SvnTest extends TestCase
{

    /**
     * @expectedException \TikiManager\Application\Exception\VcsConflictException
     */
    public function testConflictsDetectionOnUpdate()
    {
        $instance = $this->createMock(Instance::class);

        $stream = vfsStream::setup('instance');
        $instance->webroot = $stream->url();
        $instance->type = 'local';

        $stub = $this->getMockBuilder(Svn::class)
            ->setConstructorArgs([$instance])
            ->setMethods(['merge', 'info', 'ensureTempFolder'])
            ->getMock();

        $conflictMessage = <<<TXT
--- Merging r76419 into '.':
C    lib/core/Search/ContentSource/TrackerFieldSource.php
U    lib/jquery_tiki/tiki-trackers.js
 U   .
Summary of conflicts:
  Text conflicts: 1
TXT;

        $info = [
            'repository' => [
                'root' => 'https://svn.code.sf.net/p/tikiwiki/code'
            ]
        ];

        $stub->expects($this->once())->method('info')
            ->will($this->returnValue($info));

        $stub->expects($this->once())->method('merge')
            ->will($this->returnValue($conflictMessage));

        $stub->update($instance->webroot, 'trunk');
    }

    /**
     * @expectedException \TikiManager\Application\Exception\VcsConflictException
     */
    public function testConflictsDetectionOnUpgrade()
    {
       $instance = $this->createMock(Instance::class);

        $stream = vfsStream::setup('instance');
        $instance->webroot = $stream->url();
        $instance->type = 'local';

        $access = $this->getMockBuilder(Local::class)
            ->setConstructorArgs([$instance])
            ->getMock();

        $command = $this->createMock(Command::class);
        // SVN SWITCH outputs 0 with conflicts
        $command->method('getReturn')->willReturn(0);
        $conflictError = <<<TXT
A    .eslintignore
A    .eslintrc
U    tiki-download_file.php
 U   .
Updated to revision 76427.
Summary of conflicts:
  Text conflicts: 1
TXT;

        $command->method('getStdoutContent')->willReturn($conflictError);

        $access->method('runCommand')->willReturn($command);
        $instance->expects($this->atLeastOnce())
            ->method('getBestAccess')
            ->willReturn($access);

        $svn = $this->getMockBuilder(Svn::class)
            ->setConstructorArgs([$instance])
            ->setMethods(['revert'])
            ->getMock();

        $svn->method('revert')->willReturn(true);
        $svn->upgrade($instance->webroot, 'trunk');
    }

}
