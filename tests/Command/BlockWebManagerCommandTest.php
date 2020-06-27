<?php
/**
 * @copyright (c) Copyright by authors of the Tiki Manager Project. All Rights Reserved.
 *     See copyright.txt for details and a complete list of authors.
 * @licence Licensed under the GNU LESSER GENERAL PUBLIC LICENSE. See LICENSE for details.
 */

namespace TikiManager\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use TikiManager\Application\Instance;
use TikiManager\Command\BlockWebManagerCommand;
use TikiManager\Command\RestoreInstanceCommand;
use TikiManager\Command\UpgradeInstanceCommand;
use TikiManager\Config\App;
use TikiManager\Libs\Helpers\VersionControl;
use TikiManager\Tests\Helpers\Instance as InstanceHelper;

/**
 * Class BlockWebManagerCommandTester
 * @group Commands
 * @backupGlobals true
 */
class BlockWebManagerCommandTester extends TestCase
{
    public function testResetLoginAttempts()
    {
        $info = App::get('info');
        $info->update('login_attempts', 8);
        self::assertEquals(8, $info->get('login_attempts'));

        $application = new Application();
        $application->add(new BlockWebManagerCommand());
        $command = $application->find('webmanager:block');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'command'  => $command->getName(),
            '--reset'  => true
        ]);

        self::assertEquals(0, $info->get('login_attempts'));
        $this->assertContains('WebManager login attempts were reset.', $commandTester->getDisplay());
    }
}
